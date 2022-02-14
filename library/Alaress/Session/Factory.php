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
use Schoolbox\Auth\Remember\Remember;
use Schoolbox\IConfigLoader;

class Factory
{
    /**
     * @var array
     */
    protected array $cookies;

    /**
     * @var IConfigLoader
     */
    protected IConfigLoader $config;

    /**
     * @var int
     */
    protected int $requestTime;

    /**
     * Session rememberer
     */
    protected Remember $rememberer;

    /**
     * Constructor
     *
     * @param array         $cookies
     * @param IConfigLoader $config
     * @param Remember      $rememberer
     * @param int           $requestTime
     */
    public function __construct(array $cookies, IConfigLoader $config, Remember $rememberer, int $requestTime)
    {
        $this->cookies     = $cookies;
        $this->rememberer  = $rememberer;
        $this->config      = $config;
        $this->requestTime = $requestTime;
    }

    /**
     * Creates session handler.
     *
     * @return Session
     */
    public function build()
    {
        $func             = new Phpfunc($this->config, $this->rememberer);
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
