<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Realtime\Subscription\Redis;

use Alaress\Async\Notification\Instance\Instance;
use Alaress\Async\Notification\Parameter\Param;
use Predis;

/**
 * Unread retriever class, moved into a separate object so that systems outside
 * of the socket daemon can fetch and count the unread messages for a user.
 */
class UnreadRetriever
{
    /**
     * @var Predis\ClientInterface
     */
    private $syncClient;


    /**
     * Constructor
     *
     * @param Predis\ClientInterface $syncClient
     */
    public function __construct(Predis\ClientInterface $syncClient)
    {
        $this->syncClient = $syncClient;
    }


    /**
     * Returns the messages contained within this subscription.
     *
     * @param string $instanceId
     * @param int    $userId
     * @return array
     */
    public function getUnread(string $instanceId, int $userId): array
    {
        $setIdentifier = sprintf(Subscription::PATTERN_SET, $instanceId, $userId);
        return array_filter(
            array_map(
                function ($messageKey) use ($setIdentifier) {
                    try {
                        $data = $this->syncClient->get($messageKey);
                        if (!$data) {
                            // the message doesn't exist? remove the reference
                            // (precedent: `\Alaress\Async\Queue\RedisQueue::fetch`)
                            $this->syncClient->srem($setIdentifier, $messageKey);
                            return false;
                        }
                        return unserialize(
                            gzuncompress($data),
                            ['allowed_classes' => [Instance::class, Param::class,],]
                        );
                    } catch (\ErrorException $e) {
                        return false;
                    }
                },
                $this->syncClient->smembers($setIdentifier)
            ),
            function ($item) {
                return $item instanceof Instance;
            }
        );
    }
}
