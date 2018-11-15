<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Alaress\Web\Output\DataGenerator;

use Alaress\Session\Session;
use Schoolbox\IConfigLoader;

/**
 * Class Websocket
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
     * @var array
     */
    private $serverVars;


    /**
     * Constructor
     *
     * @param IConfigLoader $config
     * @param Session       $session
     * @param array         $serverVars
     */
    public function __construct(IConfigLoader $config, Session $session, array $serverVars)
    {
        $this->config     = $config;
        $this->session    = $session;
        $this->serverVars = $serverVars;
    }


    /**
     * @return array
     */
    public function generate(): array
    {
        return [
            'enabled'    => $this->config->get('messaging_enabled'),
            'host'       => $this->serverVars['HTTP_HOST'] ?? '',
            'port'       => $this->config->get('websocket_port_ssl') ?: 8081,
            'path'       => $this->config->get('websocket_path') ?: '/',
            'secure'     => true,
            'unread'     => $this->session->get('msg-count'),
            'stackLimit' => \Schoolbox\Realtime\Notification\Decorator::STACK_LIMIT,
        ];
    }
}
