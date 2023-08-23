<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Session;

use Schoolbox\Auth\Remember;
use Schoolbox\IConfigLoader;

class Phpfunc extends \Aura\Session\Phpfunc
{
    /**
     * @var IConfigLoader
     */
    private $config;

    /**
     * Session rememberer
     */
    private Remember\Remember $rememberer;

    /**
     * Constructor
     *
     * @param IConfigLoader     $config
     * @param Remember\Remember $rememberer
     */
    public function __construct(IConfigLoader $config, Remember\Remember $rememberer)
    {
        $this->config     = $config;
        $this->rememberer = $rememberer;
    }


    /**
     * @see session_start
     */
    public function session_start()
    {
        // start of with doing whatever the parent wanted to do
        $result = parent::__call('session_start', []);

        // If session wasn't started: no point doing anything else
        if (!$result) {
            return $result;
        }

        // if session ID exists, do no more
        if (!(empty($_SESSION['id']))) {
            return $result;
        }

        // no remembery? do nothing more
        if (!$this->config->get('session_remember')) {
            return $result;
        }

        // challenge 'remember me' auth
        $username = $this->rememberer->retrieveUsername();
        if (strlen($username ?? '')) {
            // found a redis match; attempt to rebuild that session

            // For safety's sake regenerate their session ID
            session_regenerate_id(true);

            // regenerate session from username
            // TODO replace cantankerous old handler
            $session = new \Session();
            $session->disableLoginLog();

            if ($session->create($username)) {
                $this->rememberer->rememberUsername($username, Remember\Remember::DEFAULT_EXPIRY);
            }
        }
        return $result;
    }
}
