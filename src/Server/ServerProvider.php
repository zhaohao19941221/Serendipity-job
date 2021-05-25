<?php
declare(strict_types = 1);

namespace Serendipity\Job\Server;

use Serendipity\Job\Contract\LoggerInterface;
use Serendipity\Job\Kernel\Provider\AbstractProvider;
use Serendipity\Job\Kernel\Swow\ServerFactory;
use Swow\Buffer;
use Swow\Coroutine;
use Swow\Socket;
use Swow\Socket\Exception;

class ServerProvider extends AbstractProvider
{
    protected LoggerInterface $logger;

    public function bootApp() : void
    {
        /**
         * @var Socket $server
         */
        $server       = $this->container()->make(ServerFactory::class)->start();
        $this->logger = $this->container()->get(LoggerInterface::class);
        $this->logger->debug('Serendipity-Job Start Successfully#');
        while (true) {
            $client = $server->accept();
            Coroutine::run(function () use ($client)
            {
                $buffer = new Buffer();
                try {
                    while (true) {
                        $length  = $client->recv($buffer);
                        $content = $buffer->getContents();
                        $this->logger->debug(sprintf('Buffer Content: %s', $content) . PHP_EOL);
                        if ($length === 0) {
                            break;
                        }
                        $offset = 0;
                        while (true) {
                            $eof = strpos($buffer->toString(), "\r\n\r\n", $offset);
                            if ($eof === false) {
                                break;
                            }
                            $client->sendString(
                                "HTTP/1.1 200 OK\r\n" .
                                "Connection: keep-alive\r\n" .
                                "Content-Length: 0\r\n\r\n"
                            );
                            $requestLength = $eof + strlen("\r\n\r\n");
                            if ($requestLength === $length) {
                                $buffer->clear();
                                break;
                            }  /* < */
                            $offset += $requestLength;
                        }
                    }
                } catch (Exception $exception) {
                    echo "No.{$client->getFd()} goaway! {$exception->getMessage()}" . PHP_EOL;
                }
            });
        }
    }
}
