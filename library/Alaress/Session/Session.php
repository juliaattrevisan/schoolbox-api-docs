<?php
/**
 * Overrides Aura\Session\Session to provide easy access to the logged-in user.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Alaress\Session;
use Aura\Session\Session as BaseSession;
use Schoolbox\User;
use Schoolbox\IConfigLoader;

class Session extends BaseSession {

    /**
     * The loader for config settings.
     *
     * @var IConfigLoader
     */
    protected $config;

    /**
     * The time at which this request was made, usually $_SERVER[REQUEST_TIME].
     *
     * @var int
     */
    protected $requestTime;

    /**
     * Returns the currently logged-in user, or an empty user if none is logged
     * in or their session has timed out.
     *
     * @return User
     */
    public function getUser() {
        $authSession   = $this->newSegment(null);
        $lastTime      = $authSession->lasttime;
        $sessionLength = $this->getConfig()->get('session_length');

        // Session timed out: don't get the user.
        $user = null;
        if (!($lastTime + $sessionLength < $this->getRequestTime())) {
            $user = $authSession->user;
        }
        return ($user instanceof User) ? $user : new User();
    }

    /**
     * @param  IConfigLoader $config
     * @return $this
     */
    public function setConfig($config) {
        $this->config = $config;
        return $this;
    }

    /**
     * @return IConfigLoader
     */
    public function getConfig() {
        return $this->config;
    }

    /**
     * @param  int   $requestTime
     * @return $this
     */
    public function setRequestTime($requestTime) {
        $this->requestTime = $requestTime;
        return $this;
    }

    /**
     * @return int
     */
    public function getRequestTime() {
        return $this->requestTime;
    }

}
