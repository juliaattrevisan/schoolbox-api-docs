<?php

/**
 * A factory method which builds the session handler.
 *
 * Implemented because Aura's segment handler doesn't retrieve session fields
 * without a namespace, and that is where the user session is currently stored.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Alaress\Session;

use Aura\Session as SessionLibrary;
use Predis\ClientInterface;
use Schoolbox\Auth\Remember\Remember;
use Schoolbox\IConfigLoader;

class Factory
{
    /**
     * Constructor
     *
     * @param array           $cookies      Contents of $_COOKIE
     * @param IConfigLoader   $config       Config store
     * @param Remember        $rememberer   Session rememberer
     * @param int             $requestTime  When was this request made?
     * @param ClientInterface $sessionRedis The Redis instance which stores session data:
     *                                      required so that if a session is created it is cut short.
     */
    public function __construct(
        private array $cookies,
        private IConfigLoader $config,
        private Remember $rememberer,
        private int $requestTime,
        private ClientInterface $sessionRedis,
    ) {
    }

    /**
     * Creates session handler.
     *
     * @return Session
     */
    public function build()
    {
        $func             = new Phpfunc($this->config, $this->rememberer, $this->sessionRedis);
        $segmentFactory   = new SegmentFactory();
        $csrfTokenFactory = new SessionLibrary\CsrfTokenFactory(new SessionLibrary\Randval());
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
