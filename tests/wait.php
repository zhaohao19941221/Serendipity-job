<?php
/**
 * This file is part of Serendipity Job
 * @license  https://github.com/Hyperf-Glory/SerendipityJob/main/LICENSE
 */

declare(strict_types=1);

use Swow\Signal;

$pid = getmypid();
$count = 3;

echo "Press Ctrl + C\n";

do {
    Signal::wait(Signal::INT);
    var_dump(\Swow\Coroutine::getCurrent()
        ->getId());
    echo "\n"; // for ^C
} while ($count-- && print_r("Repeat {$count} times if you want to quit\n"));

echo "Quit\n";