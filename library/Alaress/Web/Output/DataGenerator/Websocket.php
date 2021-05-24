<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Web\Output\DataGenerator;

use Alaress\Async\Notification\NotificationBundle;
use Alaress\Session\Session;
use Schoolbox\IConfigLoader;
use Schoolbox\Realtime\Subscription\Redis\UnreadRetriever;
use Schoolbox\User as UserModel;

/**
 * Generate data for rendering the websocket (instant messaging) system.
 */
class Websocket implements IDataGenerator
{
    /**
     * @var IConfigLoader
     */
    private $config;


    /**
     * @var UserModel
     */
    private $currentUser;


    /**
     * @var UnreadRetriever
     */
    private $unreadRetriever;


    /**
     * @var array
     */
    private $serverVars;


    /**
     * @var string
     */
    private $instanceId;


    /**
     * Constructor
     *
     * @param IConfigLoader   $config
     * @param UserModel       $currentUser
     * @param UnreadRetriever $unreadRetriever
     * @param array           $serverVars
     * @param string          $instanceId
     */
    public function __construct(
        IConfigLoader $config,
        UserModel $currentUser,
        UnreadRetriever $unreadRetriever,
        array $serverVars,
        string $instanceId
    ) {
        $this->config          = $config;
        $this->currentUser     = $currentUser;
        $this->unreadRetriever = $unreadRetriever;
        $this->serverVars      = $serverVars;
        $this->instanceId      = $instanceId;
    }


    /**
     * @inheritdoc
     */
    public function generate(?array $chromeData = []): array
    {
        $userId = $this->currentUser->getId();
        if ($userId) {
            $unread = $this->unreadRetriever->getUnread($this->instanceId, $userId);
            $bundle = new NotificationBundle();
            $bundle->appendInstances($unread);

            $enabled     = $this->config->get('messaging_enabled');
            $unreadCount = $bundle->count();
        } else {
            $enabled     = false;
            $unreadCount = 0;
        }
        return [
            'enabled'    => $enabled,
            'host'       => $this->serverVars['HTTP_X_FORWARDED_HOST'] ?? $this->serverVars['HTTP_HOST'] ?? '',
            'port'       => $this->config->get('websocket_port_ssl') ?: 8081,
            'path'       => $this->config->get('websocket_path') ?: '/',
            'secure'     => true,
            'unread'     => $unreadCount,
            'stackLimit' => \Schoolbox\Realtime\Notification\Decorator::STACK_LIMIT,
        ];
    }
}
