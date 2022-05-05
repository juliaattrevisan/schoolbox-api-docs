<?php

/**
 * @author      Justin Clarke <justin.clarke@schoolbox.com.au>
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\APIUser\Notify;

use Alaress\Async\Notification\Instance\Builder as NotificationInstanceBuilder;
use Alaress\Async\Notification\Parameter\Param;
use Alaress\Async\Notification\Parameter\ParameterInterface;
use Alaress\Async\Queue\Composite;
use Alaress\Redis;
use Alaress\Web\Controller\Base;
use Monolog\Logger;
use Predis\ClientInterface;
use Schoolbox\Realtime\Message\NSMapper;
use Schoolbox\Realtime\MessageList\Listed;
use Schoolbox\Realtime\MessageList\Unread;
use Schoolbox\User;

class Controller extends Base
{
    /**
     * Send a test notification to a user.
     *
     * @param User $user
     * @return void
     * @api POST /api/user/{userId}/notify
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function apiUserNotify(User $user): void
    {
        $data   = json_decode(json_encode($this->request->content->get()), true);
        $userId = $user->getId();
        $bob    = new NotificationInstanceBuilder();
        $bob->setTitle(($data['message'] ?? '') . ' :time')
            ->setAllowSelfSend(true)
            ->setSeverity(Logger::INFO)
            ->setNamespace(NSMapper::NS_MESSAGE)
            ->setHref($data['href'] ?? '#')
            ->setParam('time', new Param((new \Alaress_Date_Format())->getLocal(), ParameterInterface::STACKING_IGNORE))
            ->setIcon('/images/icon.php?size=64&icon=' . NSMapper::NS_MESSAGE)
            ->addTopic("instant.user.$userId")
            ->putMeta('title', 'Message from ' . ($data['from']['fullName'] ?? 'Unknown User'));

        /** @var Redis|ClientInterface $redis */
        $redis = $this->getDi()->get('redis.storage');

        $composite = new Composite(
            new Listed($redis, INSTANCE_ID, $user->getId()),
            new Unread($redis, INSTANCE_ID, $user->getId())
        );
        $composite->push($bob->build());
    }
}
