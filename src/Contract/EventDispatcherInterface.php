<?php
/**
 * This file is part of Serendipity Job
 * @license  https://github.com/Hyperf-Glory/SerendipityJob/main/LICENSE
 */

declare(strict_types=1);

namespace Serendipity\Job\Contract;

interface EventDispatcherInterface
{
    public function dispatch(object $event, string $eventName = null): object;
}
