<?php
/**
 * This file is part of Serendipity Job
 * @license  https://github.com/Hyperf-Glory/Serendipity-job/blob/main/LICENSE
 */

declare(strict_types=1);

namespace Serendipity\Job\Kernel\Dag;

use JetBrains\PhpStorm\Pure;

class Vertex
{
    public ?string $key = '';

    public ?int $timeout = 0;

    /**
     * @var callable
     */
    public $value;

    /**
     * @var array<Vertex>
     */
    public array $parents = [];

    /**
     * @var array<Vertex>
     */
    public array $children = [];

    public static function make(callable $job, int $timeout = 5, string $key = null, ): self
    {
        $closure = \Closure::fromCallable($job);
        if ($key === null) {
            $key = spl_object_hash($closure);
        }

        $v = new self();
        $v->key = $key;
        $v->timeout = $timeout;
        $v->value = $closure;

        return $v;
    }

    #[Pure]
    public static function of(
        Runner $job,
        int $timeout = 5,
        string $key = null,
    ): self {
        if ($key === null) {
            $key = spl_object_hash($job);
        }

        $v = new self();
        $v->key = $key;
        $v->value = [$job, 'run'];
        $v->timeout = $timeout;

        return $v;
    }
}
