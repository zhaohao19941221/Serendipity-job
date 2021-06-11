<?php

declare( strict_types = 1 );

namespace Serendipity\Job\Util;

use Serendipity\Job\Contract\StdoutLoggerInterface;
use Hyperf\Engine\Coroutine as Co;
use Hyperf\Engine\Exception\CoroutineDestroyedException;
use Hyperf\Engine\Exception\RunningInNonCoroutineException;
use Psr\Log\LoggerInterface;
use Throwable;
use function Serendipity\Job\Kernel\serendipity_call;

class Coroutine
{
    /**
     * Returns the current coroutine ID.
     * Returns -1 when running in non-coroutine context.
     */
    public static function id (): int
    {
        return Co::id();
    }

    public static function defer (callable $callable): void
    {
        Co::defer($callable);
    }

    public static function sleep (float $seconds): void
    {
        usleep((int) ( $seconds * 1000 * 1000 ));
    }

    /**
     * Returns the parent coroutine ID.
     * Returns 0 when running in the top level coroutine.
     *
     * @throws RunningInNonCoroutineException when running in non-coroutine context
     * @throws CoroutineDestroyedException when the coroutine has been destroyed
     */
    public static function parentId (?int $coroutineId = null): int
    {
        return Co::pid($coroutineId);
    }

    /**
     * @return int Returns the coroutine ID of the coroutine just created.
     *             Returns -1 when coroutine create failed.
     */
    public static function create (callable $callable): int
    {
        $coroutine = Co::create(function () use ($callable) {
            try {
                serendipity_call($callable);
            } catch (Throwable $throwable) {
                if (ApplicationContext::hasContainer()) {
                    $container = ApplicationContext::getContainer();
                    if ($container->has(StdoutLoggerInterface::class)) {
                        /* @var LoggerInterface $logger */
                        $logger = $container->get(StdoutLoggerInterface::class);
                        $logger->warning(sprintf('Uncaptured exception[%s] detected in %s::%d.', get_class($throwable),
                            $throwable->getFile(), $throwable->getLine()));
                    }
                }
            }
        });

        try {
            return $coroutine->getId();
        } catch (\Throwable) {
            return -1;
        }
    }

    public static function inCoroutine (): bool
    {
        return Co::id() > 0;
    }
}
