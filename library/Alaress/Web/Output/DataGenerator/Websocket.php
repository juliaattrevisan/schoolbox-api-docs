<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Alaress\Web\Output\DataGenerator;

use Alaress\Async\Notification\NotificationBundle;
use Alaress\Session\Session;
use Predis\ClientInterface;
use Schoolbox\IConfigLoader;
use Alaress\Async\Notification\Instance\Instance;
use Alaress\Async\Notification\Parameter\Param;

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
     * @var ClientInterface
     */
    private $redis;

    /**
     * @var array
     */
    private $serverVars;


    /**
     * Constructor
     *
     * @param IConfigLoader   $config
     * @param Session         $session
     * @param ClientInterface $redis
     * @param array           $serverVars
     */
    public function __construct(IConfigLoader $config, Session $session, ClientInterface $redis, array $serverVars)
    {
        $this->config     = $config;
        $this->session    = $session;
        $this->redis      = $redis;
        $this->serverVars = $serverVars;
    }


    /**
     * @inheritdoc
     */
    public function generate(?array $chromeData = []): array
    {
        return [
            'enabled'    => $this->config->get('messaging_enabled'),
            'host'       => $this->serverVars['HTTP_HOST'] ?? '',
            'port'       => $this->config->get('websocket_port_ssl') ?: 8081,
            'path'       => $this->config->get('websocket_path') ?: '/',
            'secure'     => true,
            'unread'     => $this->getUnreadMessages(),
            'stackLimit' => \Schoolbox\Realtime\Notification\Decorator::STACK_LIMIT,
        ];
    }

    public function getUnreadMessages() {
        $intances =
            array_filter(
                array_map(
                    function ($messageKey) {
                        try {
                            $data = $this->redis->get($messageKey);
                            if (!$data) {
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
                $this->redis->sMembers(sprintf('msg:%s:q:%d:unread', INSTANCE_ID,  $this->session->getAuthUser()->getId()))
                ),
                function ($item) {
                    return $item instanceof Instance;
                }
            );

        $notfication = new NotificationBundle();
        $notfication->appendInstances($intances);
        return $notfication->count();
    }

}
