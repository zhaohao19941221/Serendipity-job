<?php
/**
 * This file is part of Serendipity Job
 * @license  https://github.com/serendipitySwow/Serendipity-job/blob/main/LICENSE
 */

declare(strict_types=1);

namespace Serendipity\Job\Crontab;

use Carbon\Carbon;
use Closure;
use Psr\Container\ContainerInterface;
use Serendipity\Job\Contract\LoggerInterface;

class Executor
{
    protected ContainerInterface $container;

    /**
     * @var null|LoggerInterface
     */
    protected mixed $logger;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        if ($container->has(LoggerInterface::class)) {
            $this->logger = $container->get(LoggerInterface::class);
        } elseif ($container->has(StdoutLoggerInterface::class)) {
            $this->logger = $container->get(StdoutLoggerInterface::class);
        }
    }

    public function execute(Crontab $crontab): void
    {
        if (!$crontab instanceof Crontab || !$crontab->getExecuteTime()) {
            return;
        }
        $diff = $crontab->getExecuteTime()->diffInRealSeconds(new Carbon());
        $callback = null;
        switch ($crontab->getType()) {
            case 'callback':
                //TODO 任务一直没执行
                [$class, $method] = $crontab->getCallback();
                $parameters = $crontab->getCallback()[2] ?? null;
                if ($class && $method && class_exists($class) && method_exists($class, $method)) {
                    $callback = function () use ($class, $method, $parameters, $crontab) {
                        $runnable = function () use ($class, $method, $parameters, $crontab) {
                            try {
                                $result = true;
                                $instance = make($class);
                                if ($parameters && is_array($parameters)) {
                                    $instance->{$method}(...$parameters);
                                } else {
                                    $instance->{$method}();
                                }
                            } catch (\Throwable $throwable) {
                                $result = false;
                            } finally {
                                $this->logResult($crontab, $result);
                            }
                        };
                        Coroutine::create($this->decorateRunnable($crontab, $runnable));
                    };
                }
                break;
            case 'eval':
                $callback = function () use ($crontab) {
                    $runnable = function () use ($crontab) {
                        eval($crontab->getCallback());
                    };
                    $this->decorateRunnable($crontab, $runnable)();
                };
                break;
        }
//        $callback && Timer::after($diff > 0 ? $diff * 1000 : 1, $callback);
    }

    protected function decorateRunnable(Crontab $crontab, Closure $runnable): Closure
    {
        return $runnable;
    }

    protected function logResult(Crontab $crontab, bool $isSuccess): void
    {
        if ($this->logger) {
            if ($isSuccess) {
                $this->logger->info(sprintf('Crontab task [%s] executed successfully at %s.', $crontab->getName(), date('Y-m-d H:i:s')));
            } else {
                $this->logger->error(sprintf('Crontab task [%s] failed execution at %s.', $crontab->getName(), date('Y-m-d H:i:s')));
            }
        }
    }
}