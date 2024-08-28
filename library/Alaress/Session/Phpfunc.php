<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Session;

use Predis\ClientInterface;
use Schoolbox\Auth\Remember;
use Schoolbox\IConfigLoader;
use Schoolbox\Realtime\Pipe\PipeManager;

/**
 * Extends Aura's PHP function wrapper to override `session_start`, so that it:
 * * sets a max session lifetime of 5 minutes for new sessions, regardless of
 *   session lifetime
 * * restores a session from remember me, if there is one
 */
class Phpfunc extends \Aura\Session\Phpfunc
{
    /**
     * Expire sessions created after 5 minutes, instead of the (presumably
     * longer!) default expiry.
     *
     * (If a session created is reused, its expiry will be extended as per
     * normal.)
     */
    private const API_SESSION_EXPIRY = 300;

    /**
     * Constructor
     *
     * @param IConfigLoader     $config       Config store
     * @param Remember\Remember $rememberer   Session rememberer
     * @param ClientInterface   $sessionRedis The Redis instance which stores session data:
     *                                        required so that if a session is created it is cut short.
     */
    public function __construct(
        private IConfigLoader $config,
        private Remember\Remember $rememberer,
        private ClientInterface $sessionRedis,
    ) {
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

        // otherwise, this is a new session: set its expiry to a default value
        register_shutdown_function(function (): void {
            session_write_close();
            $this->sessionRedis->expire(PipeManager::REDIS_SESSION_PREFIX . session_id(), self::API_SESSION_EXPIRY);
        });

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
