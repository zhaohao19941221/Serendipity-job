<?php
/**
 * This file is part of Serendipity Job
 * @license  https://github.com/serendipitySwow/Serendipity-job/blob/main/LICENSE
 */

declare(strict_types=1);

namespace Serendipity\Job\Console;

use Carbon\Carbon;
use Hyperf\Utils\ApplicationContext;
use Psr\Container\ContainerInterface;
use Serendipity\Job\Constant\Task;
use Serendipity\Job\Contract\ConfigInterface;
use Serendipity\Job\Contract\EventDispatcherInterface;
use Serendipity\Job\Contract\SerializerInterface;
use Serendipity\Job\Contract\StdoutLoggerInterface;
use Serendipity\Job\Crontab\CrontabDispatcher;
use Serendipity\Job\Db\DB;
use Serendipity\Job\Event\CrontabEvent;
use Serendipity\Job\Kernel\Provider\KernelProvider;
use Serendipity\Job\Nsq\Consumer\AbstractConsumer;
use Serendipity\Job\Nsq\Consumer\DagConsumer;
use Serendipity\Job\Nsq\Consumer\TaskConsumer;
use SerendipitySwow\Nsq\Message;
use SerendipitySwow\Nsq\Nsq;
use SerendipitySwow\Nsq\Result;
use Swow\Coroutine;
use Swow\Coroutine\Exception as CoroutineException;
use Swow\Http\Buffer;
use Swow\Http\Exception as HttpException;
use Swow\Http\Server as HttpServer;
use Swow\Http\Status;
use Swow\Http\Status as HttpStatus;
use Swow\Socket\Exception as SocketException;
use Symfony\Component\Console\Input\InputOption;
use Throwable;
use const Swow\Errno\EMFILE;
use const Swow\Errno\ENFILE;
use const Swow\Errno\ENOMEM;

final class ManageJobCommand extends Command
{
    public static $defaultName = 'scheduler:consume';

    protected const COMMAND_PROVIDER_NAME = 'Consumer-Job';

    protected const TASK_TYPE = [
        'dag',
        'task',
    ];

    public const TOPIC_PREFIX = 'serendipity-job-';

    protected ?ConfigInterface $config = null;

    protected ?StdoutLoggerInterface $stdoutLogger = null;

    protected ?SerializerInterface $serializer = null;

    protected ?Nsq $subscriber = null;

    protected function configure(): void
    {
        $this
            ->setDescription('Consumes tasks')
            ->setDefinition([
                new InputOption(
                    'type',
                    't',
                    InputOption::VALUE_REQUIRED,
                    'Select the type of task to be performed (dag, task),',
                    'task'
                ),
                new InputOption(
                    'limit',
                    'l',
                    InputOption::VALUE_REQUIRED,
                    'Configure the number of coroutines to process tasks',
                    1
                ),
                new InputOption(
                    'host',
                    'host',
                    InputOption::VALUE_REQUIRED,
                    'Configure HttpServer host',
                    '127.0.0.1'
                ),
                new InputOption(
                    'port',
                    'p',
                    InputOption::VALUE_REQUIRED,
                    'Configure HttpServer port numbers',
                    9764
                ),
            ])
            ->setHelp(
                <<<'EOF'
                    The <info>%command.name%</info> command consumes tasks

                        <info>php %command.full_name%</info>

                    Use the --limit option configure the number of coroutines to process tasks:
                        <info>php %command.full_name% --limit=10</info>
                    Use the --type option Select the type of task to be performed (dag, task),If you choose dag, limit is best configured to 1:
                        <info>php %command.full_name% --type=task</info>
                    Use the --host Configure HttpServer host:
                        <info>php %command.full_name% --host=127.0.0.1</info>
                    Use the --type Configure HttpServer port numbers:
                        <info>php %command.full_name% --port=9764</info>
                    EOF
            );
    }

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        parent::__construct();
    }

    public function handle(): int
    {
        $this->bootStrap();
        $this->config = $this->container->get(ConfigInterface::class);
        $this->stdoutLogger = $this->container->get(StdoutLoggerInterface::class);
        $this->stdoutLogger->info('Consumer Task Successfully Processed#');
        $limit = $this->input->getOption('limit');
        $type = $this->input->getOption('type');
        $port = (int) $this->input->getOption('port');
        $host = $this->input->getOption('host');
        if (!in_array($type, self::TASK_TYPE, true)) {
            $this->stdoutLogger->error('Invalid task parameters.');
            exit(1);
        }
        if ($limit !== null) {
            for ($i = 0; $i < $limit; $i++) {
                $this->subscribe($i, $type);
                $this->stdoutLogger->info(ucfirst($type) . 'Consumerd#' . $i . ' start.');
            }
        }
        $this->makeServer($host, $port);

        return Command::SUCCESS;
    }

    protected function makeServer(string $host, int $port): void
    {
        $server = new HttpServer();
        $server->bind($host, $port)
            ->listen();
        while (true) {
            try {
                $session = $server->acceptSession();
                Coroutine::run(function () use ($session) {
                    try {
                        while (true) {
                            $request = null;
                            try {
                                $request = $session->recvHttpRequest();
                                switch ($request->getPath()) {
                                    case '/':
                                    {
                                        $buffer = new Buffer();
                                        $buffer->write(file_get_contents(BASE_PATH . '/storage/task.php'));
                                        $response = new HttpServer\Response();
                                        $response->setStatus(Status::OK);
                                        $response->setHeader('Server', 'Serendipity-Job');
                                        $response->setBody($buffer);
                                        $session->sendHttpResponse($response);
                                        break;
                                    }
                                    case '/cancel':
                                        $params = $request->getQueryParams();
                                        $coroutine = Coroutine::get((int) $params['coroutine_id']);
                                        $buffer = new Buffer();
                                        $response = new HttpServer\Response();
                                        $response->setStatus(Status::OK);
                                        $response->setHeader('Server', 'Serendipity-Job');
                                        if (!$coroutine instanceof Coroutine) {
                                            $buffer->write(json_encode([
                                                'code' => 1,
                                                'msg' => 'Unknown!',
                                                'data' => [],
                                            ], JSON_THROW_ON_ERROR));
                                            $response->setBody($buffer);
                                            $session->sendHttpResponse($response);
                                            break;
                                        }
                                        if ($coroutine === Coroutine::getCurrent()) {
                                            $session->respond(json_encode([
                                                'code' => 1,
                                                'msg' => '参数错误!',
                                                'data' => [],
                                            ], JSON_THROW_ON_ERROR));
                                            break;
                                        }
                                        if ($coroutine->getState() === $coroutine::STATE_LOCKED) {
                                            $buffer->write(json_encode([
                                                'code' => 1,
                                                'msg' => 'coroutine block object locked	!',
                                                'data' => [],
                                            ], JSON_THROW_ON_ERROR));
                                            $response->setBody($buffer);
                                            $session->sendHttpResponse($response);
                                            break;
                                        }
                                        $coroutine->kill();
                                        if ($coroutine->isAvailable()) {
                                            $buffer->write(json_encode([
                                                'code' => 1,
                                                'msg' => 'Not fully killed, try again later...',
                                                'data' => [],
                                            ], JSON_THROW_ON_ERROR));
                                        } else {
                                            DB::execute(sprintf(
                                                "update task set status  = %s,memo = '%s' where coroutine_id = %s and status = %s and id = %s",
                                                Task::TASK_CANCEL,
                                                sprintf(
                                                    '客户度IP:%s取消了任务,请求时间:%s.',
                                                    $session->getPeerAddress(),
                                                    Carbon::now()
                                                        ->toDateTimeString()
                                                ),
                                                $params['coroutine_id'],
                                                Task::TASK_ING,
                                                $params['id']
                                            ));
                                            $buffer->write(json_encode([
                                                'code' => 0,
                                                'msg' => 'Killed',
                                                'data' => [],
                                            ], JSON_THROW_ON_ERROR));
                                        }
                                        $response->setBody($buffer);
                                        $session->sendHttpResponse($response);
                                        break;
                                    default:
                                    {
                                        $session->error(HttpStatus::NOT_FOUND);
                                    }
                                }
                            } catch (HttpException $exception) {
                                $session->error($exception->getCode(), $exception->getMessage());
                            }
                            if (!$request || !$request->getKeepAlive()) {
                                break;
                            }
                        }
                    } catch (Exception $exception) {
                        // you can log error here
                    } finally {
                        $session->close();
                    }
                });
            } catch (SocketException | CoroutineException $exception) {
                if (in_array($exception->getCode(), [EMFILE, ENFILE, ENOMEM], true)) {
                    sleep(1);
                } else {
                    break;
                }
            }
        }
    }

    protected function subscribe(int $i, string $type): void
    {
        Coroutine::run(
            function () use ($i, $type) {
                /**
                 * @var NSq $subscriber
                 */
                $subscriber = make(Nsq::class, [
                    $this->container,
                    $this->config->get(sprintf('nsq.%s', 'default')),
                ]);
                $consumer = match ($type) {
                    'task' => $this->makeConsumer(TaskConsumer::class, self::TOPIC_PREFIX . $type, 'Consumerd'),
                    'dag' => $this->makeConsumer(DagConsumer::class, self::TOPIC_PREFIX . $type, 'Consumerd')
                };
                $subscriber->subscribe(
                    self::TOPIC_PREFIX . $type,
                    ucfirst($type) . 'Consumerd' . $i,
                    function (Message $message) use ($consumer, $i) {
                        try {
                            $result = $consumer->consume($message);
                        } catch (Throwable $error) {
                            $this->stdoutLogger->error(sprintf(
                                'Consumer failed to consume %s,reason: %s,file: %s,line: %s',
                                'Consumerd' . $i,
                                $error->getMessage(),
                                $error->getFile(),
                                $error->getLine()
                            ));
                            $result = Result::DROP;
                        }

                        return $result;
                    }
                );
            }
        );
    }

    protected function makeConsumer(string $class, string $topic, string $channel): AbstractConsumer
    {
        /**
         * @var AbstractConsumer $consumer
         */
        $consumer = ApplicationContext::getContainer()
            ->get($class);
        $consumer->setTopic($topic);
        $consumer->setChannel($channel);

        return $consumer;
    }

    protected function bootStrap(): void
    {
        KernelProvider::create(self::COMMAND_PROVIDER_NAME)
            ->bootApp();
        $this->dispatchCrontab();
    }

    protected function dispatchCrontab(): void
    {
        $this->container->get(EventDispatcherInterface::class)
            ->dispatch(
                new CrontabEvent(),
                CrontabEvent::CRONTAB_REGISTER
            );
        $this->container->get(CrontabDispatcher::class)->handle();
    }
}