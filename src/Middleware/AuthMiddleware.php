<?php
/**
 * This file is part of Serendipity Job
 * @license  https://github.com/serendipitySwow/Serendipity-job/blob/main/LICENSE
 */

declare(strict_types=1);

namespace Serendipity\Job\Middleware;

use Hyperf\Utils\ApplicationContext;
use InvalidArgumentException;
use Psr\Http\Message\RequestInterface;
use Serendipity\Job\Db\DB;
use Serendipity\Job\Kernel\Signature;
use Serendipity\Job\Redis\RedisFactory;
use Serendipity\Job\Util\Context;
use Swow\Http\Server\Request;

class AuthMiddleware
{
    protected Signature $signature;

    final public function process(Request $request): bool
    {
        $timestamp = $request->getHeaderLine('timestamps') ?? '';
        $nonce = $request->getHeaderLine('nonce') ?? '';
        $payload = $request->getHeaderLine('payload') ?? '';
        $appKey = $request->getHeaderLine('app_key') ?? '';
        $signature = $request->getHeaderLine('signature') ?? '';
        $application = $this->getApplication($appKey);
        $application ? $this->signature = make(Signature::class, [
            'options' => [
                'signatureSecret' => $application['secret_key'] ?? '',
                'signatureAppKey' => $appKey,
            ],
        ]) : throw new InvalidArgumentException('Unknown AppKey#');
        $this->initRequest($application);

        return $this->signature->verifySignature($timestamp, $nonce, $payload, $appKey, $signature);
    }

    /**
     * @param $appKey
     *
     * @throws \JsonException
     * @return array|bool
     */
    protected function getApplication($appKey): array | bool
    {
        $redis = ApplicationContext::getContainer()
            ->get(RedisFactory::class)
            ->get('default');
        if (!$application = $redis->get(sprintf('APP_KEY:%s', $appKey))) {
            $application = DB::fetch(sprintf(
                "SELECT * FROM application WHERE app_key = '%s' AND status = '1' AND is_deleted = '0'",
                $appKey
            ));
            $redis->set(sprintf('APP_KEY:%s', $appKey), json_encode($application, JSON_THROW_ON_ERROR), 24 * 60 * 60);
        }

        return is_string($application) && $application[0] === '{' ? json_decode(
            $application,
            true,
            512,
            JSON_THROW_ON_ERROR
        ) : $application ?? false;
    }

    protected function initRequest(mixed $application): void
    {
        /**
         * @var Request $SwowRequest
         */
        $SwowRequest = Context::get(RequestInterface::class);
        $SwowRequest = $SwowRequest->withAddedHeader('application', $application);
        Context::set(RequestInterface::class, $SwowRequest);
    }
}
