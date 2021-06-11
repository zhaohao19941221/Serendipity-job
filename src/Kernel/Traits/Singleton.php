<?php
/**
 * This file is part of Serendipity Job
 * @license  https://github.com/Hyperf-Glory/SerendipityJob/main/LICENSE
 */

declare(strict_types=1);

namespace Serendipity\Job\Kernel\Traits;

trait Singleton
{
    /**
     * @var null|Singleton
     */
    private static ?self $instance = null;

    public static function create(): static
    {
        if (self::$instance instanceof self) {
            return self::$instance;
        }

        return self::$instance = new self();
    }
}
