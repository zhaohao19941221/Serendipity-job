<?php
/**
 * This file is part of Serendipity Job
 * @license  https://github.com/serendipity-swow/serendipity-job/blob/main/LICENSE
 */

declare(strict_types=1);

namespace Serendipity\Job\Config;

use Hyperf\Contract\ConfigInterface;
use Serendipity\Job\Kernel\Provider\AbstractProvider;

class ConfigProvider extends AbstractProvider
{
    protected static string $interface = ConfigInterface::class;

    public function bootApp(): void
    {
        $this->container()
            ->make(ConfigFactory::class);
    }
}
