<?php
/**
 * A factory method which builds the session handler.
 *
 * Implemented because Aura's segment handler doesn't retrieve session fields
 * without a namespace, and that is where the user session is currently stored.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Alaress\Session;
use Alaress\Redis;
use Aura\Session as SessionLibrary;

class Factory {

    /**
     * @var array
     */
    protected $cookies;
    /**
     * @var \Schoolbox\IConfigLoader
     */
    protected $config;

    /**
     * @var int
     */
    protected $requestTime;

    /**
     * Creates factory.
     *
     * @param array $cookies Cookies, often from $_COOKIE but not always.
     * @param \Schoolbox\IConfigLoader $config
     * @param int $requestTime Unix timestamp, usually from $_SERVER['REQUEST_TIME'].
     */
    public function __construct($cookies, \Schoolbox\IConfigLoader $config, $requestTime) {
        $this->cookies = $cookies;
        $this->config = $config;
        $this->requestTime = $requestTime;
    }

    /**
     * Creates session handler.
     *
     * @return Session
     */
    public function build() {
        $func             = new Phpfunc($this->config, Redis::getClientInstance(), $this->cookies);
        $segmentFactory   = new SegmentFactory();
        $csrfTokenFactory = new SessionLibrary\CsrfTokenFactory(
            new SessionLibrary\Randval($func)
        );
        return new Session(
            $segmentFactory,
            $csrfTokenFactory,
            $func,
            $this->cookies,
            $this->config,
            $this->requestTime
        );
    }
}
