<?php
/**
 * This file is part of Serendipity Job
 * @license  https://github.com/Hyperf-Glory/Serendipity-job/blob/main/LICENSE
 */

declare(strict_types=1);
/**
 * This file is part of Task-Schedule.
 *
 * @license  https://github.com/Hyperf-Glory/Task-Schedule/main/LICENSE
 */

namespace Serendipity\Job\Dingtalk\Messages;

abstract class Message
{
    protected array $message = [];

    protected mixed $at;

    public function getMessage(): array
    {
        return $this->message;
    }

    public function sendAt($mobiles = [], $atAll = false): Message
    {
        $this->at = $this->makeAt($mobiles, $atAll);

        return $this;
    }

    public function getBody(): array
    {
        if (empty($this->at)) {
            $this->sendAt();
        }

        return $this->message + $this->at;
    }

    protected function makeAt($mobiles = [], $atAll = false): array
    {
        return [
            'at' => [
                'atMobiles' => $mobiles,
                'isAtAll' => $atAll,
            ],
        ];
    }
}
