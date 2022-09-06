<?php

/**
 * @author      Justin Clarke <justin.clarke@schoolbox.com.au>
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\APIUser\Notify;

use Alaress\Async\Notification\Instance\Builder as NotificationInstanceBuilder;
use Alaress\Async\Notification\Instance\Instance;
use Alaress\Async\Queue\Composite;
use Alaress\Web\Controller\Base;
use Monolog\Logger;
use Predis\ClientInterface;
use Schoolbox\Realtime\Message\NSMapper;
use Schoolbox\Realtime\MessageList\Listed;
use Schoolbox\Realtime\MessageList\Unread;
use Schoolbox\Realtime\Routing\Cache\WritableCache;
use Schoolbox\User;

class Controller extends Base
{
    public function __construct(
        private ClientInterface $redisStorage,
        private WritableCache $subscriptionCache
    ) {
    }

    /**
     * Send a test notification to a user.
     *
     * @param User $user
     * @return void
     * @api POST /api/user/{userId}/notify
     */
    public function apiUserNotify(User $user): void
    {
        $data = json_decode(json_encode($this->request->content->get()), true);
        $bob  = new NotificationInstanceBuilder();
        $bob->setTitle($data['message'] ?? '')
            ->setAllowSelfSend(true)
            ->setSeverity(Logger::INFO)
            ->setNamespace(NSMapper::NS_MESSAGE)
            ->setHref($data['href'] ?? '#')
            ->setIcon('/images/icon.php?size=64&icon=' . NSMapper::NS_MESSAGE)
            ->addTopic(sprintf('%s.user.%d', Instance::DELIVERY_INSTANT, $user->getId()))
            ->putMeta('title', 'Message from ' . ($data['from']['fullName'] ?? 'Unknown User'));

        $composite = new Composite(
            new Listed($this->redisStorage, INSTANCE_ID, $user->getId()),
            new Unread($this->redisStorage, INSTANCE_ID, $user->getId())
        );
        $composite->push($bob->build());
    }

    /**
     * Refreshes notification bindings
     *
     * @return void
     * @api GET /api/user/refreshBindingCache
     */
    public function apiRefreshBindingCache()
    {
        $this->subscriptionCache->cacheRedirects(INSTANCE_ID);
    }
}
