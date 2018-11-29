<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Alaress\Web\Output\DataGenerator;

use Alaress\Async\Notification\NotificationBundle;
use Alaress\Session\Session;
use Schoolbox\IConfigLoader;
use Schoolbox\Realtime\Subscription\Redis\UnreadRetriever;

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
     * @var Session
     */
    private $session;


    /**
     * @var UnreadRetriever
     */
    private $unreadCounter;


    /**
     * @var array
     */
    private $serverVars;


    /**
     * Constructor
     *
     * @param IConfigLoader   $config
     * @param Session         $session
     * @param UnreadRetriever $unreadCounter
     * @param array           $serverVars
     */
    public function __construct(
        IConfigLoader $config,
        Session $session,
        UnreadRetriever $unreadCounter,
        array $serverVars
    ) {
        $this->config        = $config;
        $this->session       = $session;
        $this->unreadCounter = $unreadCounter;
        $this->serverVars    = $serverVars;
    }


    /**
     * @inheritdoc
     */
    public function generate(?array $chromeData = []): array
    {
        $unread = $this->unreadCounter->getUnread(INSTANCE_ID, $this->session->getAuthUser()->getId());
        $bundle = new NotificationBundle();
        $bundle->appendInstances($unread);
        return [
            'enabled'    => $this->config->get('messaging_enabled'),
            'host'       => $this->serverVars['HTTP_HOST'] ?? '',
            'port'       => $this->config->get('websocket_port_ssl') ?: 8081,
            'path'       => $this->config->get('websocket_path') ?: '/',
            'secure'     => true,
            'unread'     => $bundle->count(),
            'stackLimit' => \Schoolbox\Realtime\Notification\Decorator::STACK_LIMIT,
        ];
    }
}
