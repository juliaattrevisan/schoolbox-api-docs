<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Session;

use Predis\ClientInterface;
use Schoolbox\IConfigLoader;

class Phpfunc extends \Aura\Session\Phpfunc
{

    /**
     * @var IConfigLoader
     */
    private $config;
    /**
     * @var ClientInterface
     */
    private $redis;
    /**
     * @var array
     */
    private $cookies;


    /**
     * Phpfunc constructor.
     *
     * @param IConfigLoader   $config
     * @param ClientInterface $redis
     * @param array           $cookies
     */
    public function __construct(IConfigLoader $config, ClientInterface $redis, array $cookies)
    {
        $this->config  = $config;
        $this->redis   = $redis;
        $this->cookies = $cookies;
    }


    /**
     * @see session_start
     */
    public function session_start()
    {
        // start of with doing whatever the parent wanted to do
        parent::__call('session_start', []);

        if (!(empty($_SESSION['id']))) {
            // if successful, do no more
            return true;
        }

        if (!($this->config->get('session_remember') && isset($this->cookies['remember']))) {
            // no remembery; well, that's too bad then
            return false;
        }

        // challenge 'remember me' auth
        $username = $this->redis->get($this->cookies['remember']);

        if (strlen($username)) {
            // found a redis match; attempt to rebuild that session

            // For safety's sake regenerate their session ID
            session_regenerate_id(true);

            // regenerate session from username
            // TODO replace cantankerous old handler
            $session = new \Session();
            $session->disableLoginLog();
            return $session->create($username, true);
        }

        return false;
    }
}
