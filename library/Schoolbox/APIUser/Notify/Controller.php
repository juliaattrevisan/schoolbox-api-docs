<?php

/**
 * @author      Justin Clarke <justin.clarke@schoolbox.com.au>
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\APIUser\Notify;

use Alaress\Async\Notification\Instance\Builder as NotificationInstanceBuilder;
use Alaress\Redis;
use Alaress\Web\Controller\Base;
use Monolog\Logger;
use Schoolbox\Realtime\Alert\RedisPublisher;
use Schoolbox\Realtime\Message\NSMapper;
use Schoolbox\User;

class Controller extends Base
{
    /**
     * Send a test notification to a user.
     *
     * @param User $user
     * @return void
     * @api POST /api/user/{userId}/notify
     */
    public function apiUserNotify(User $user): void
    {
        $data   = json_decode(json_encode($this->request->content->get()), true);
        $userId = $user->getId();
        $bob    = new NotificationInstanceBuilder();
        $bob->setTitle($data['message'] ?? '')
            ->setAllowSelfSend(true)
            ->setSeverity(Logger::INFO)
            ->setNamespace(NSMapper::NS_MESSAGE)
            ->setHref($data['href'] ?? '#')
            ->setIcon('/images/icon.php?size=64&icon=' . NSMapper::NS_MESSAGE)
            ->addTopic("notify.user.$userId")
            ->putMeta('ttl', 30)
            ->putMeta('title', 'Message from ' . ($data['from']['fullName'] ?? 'Unknown User'));

        (new RedisPublisher(
            Redis::getSignalQueueClientInstance(),
            INSTANCE_ID,
        ))->pushUser($userId, $bob->build());

        // Output nothing
    }
}
