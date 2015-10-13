<?php
/**
 * Overrides Aura\Session\Session to provide easy access to the following:
 * * the logged-in user
 * * the history
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Alaress\Session;
use Aura\Session\Session as BaseSession;
use Schoolbox\User;
use Schoolbox\IConfigLoader;
use Aura\Session\Phpfunc;

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
     * Constructor sets up config dependencies
     * @param SegmentFactory $segment_factory A session segment factory.
     *
     * @param \Aura\Session\CsrfTokenFactory A CSRF token factory.
     *
     * @param array $cookies An arry of cookies from the client, typically a
     * copy of $_COOKIE.
     *
     * @param \Schoolbox\IConfigLoader $config
     *
     * @param int Request time, to compare the session time against
     *
     */
    public function __construct(
        SegmentFactory   $segment_factory,
        \Aura\Session\CsrfTokenFactory $csrf_token_factory,
        PhpFunc $phpfunc,
        array $cookies = array(),
        \Schoolbox\IConfigLoader $config,
        $requestTime
    ) {
        parent::__construct($segment_factory, $csrf_token_factory, $phpfunc, $cookies);
        $this->setConfig($config);
        $this->setRequestTime($requestTime);
    }

    /**
     * Returns the currently logged-in user, or an empty user if none is logged
     * in or their session has timed out.
     *
     * @return User
     */
    public function getAuthUser() {
        $user = null;
        if ($this->isAuthSessionAlive()) {
            $user = $this->getSegment(null)->get('user');
        }
        return ($user instanceof User) ? $user : new User();
    }

    /**
     * Updates the last time that the user was accessed to whatever this
     * request's time is.
     */
    public function updateLastTime() {
        $authSession = $this->getSegment(null);
        $authSession->set('lasttime', $this->getRequestTime());
    }

    /**
     * Is the current user authentication session alive (does it exist, and has
     * it not expired?)
     *
     * @return bool
     */
    public function isAuthSessionAlive() {
        $authSession   = $this->getSegment(null);

        if($authSession->get('remember')) {
            return true;
        }
        else {
            $lastTime      = $authSession->get('lasttime');
            $sessionLength = $this->getConfig()->get('session_length');

            // Session timed out: don't get the user.
            return (!($lastTime + $sessionLength < $this->getRequestTime()));
        }
    }

    /**
     * Stores a recently visited URL in history.
     *
     * @see \SitePage->saveHistory()
     *
     * @param string $value The URL to store in history.
     */
    public function pushHistory($value) {
        $session = $this->getSegment(null);
        $history = $session->get('history');
        if (empty($history)) {
            $history = array();
        }
        else {
            // Don't save history if this is the same as the most recent item in
            // history.
            list($mostRecent) = $history;
            if ($value != $mostRecent) {
                array_unshift($history, $value);
            }
            // 3 most recent only
            $history = array_slice($history, 0, 3);
        }
        $session->set('history', $history);
    }

    /**
     * Gets an URL out of history.
     *
     * @see \SitePage->backToTheFuture()
     *
     * @param  int    $level   The xth most recent item to get.
     * @param  string $default If nothing in history, return this.
     * @return string
     */
    public function getHistory($level = 1, $default = '/') {
        $session = $this->getSegment(null);
        $history = $session->get('history');
        if (empty($history)) {
            return $default;
        }

        // Don't go beyond the beginning of recorded history
        $level = min(count($history), $level);
        return ($history[$level - 1]);
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


    /**
     * @param string $key
     * @param mixed  $alt
     * @param string $namespace
     * @return mixed
     */
    public function get($key, $alt = null, $namespace = null)
    {
        return $this->getSegment($namespace)->get($key, $alt);
    }


    /**
     * @param string $key
     * @param string $value
     * @param string $namespace
     * @return void
     */
    public function set($key, $value, $namespace = null)
    {
        $this->getSegment($namespace)->set($key, $value);
    }

}
