<?php
/**
 * A factory method which builds the session handler.
 *
 * Implemented because PHP 5.3 doesn't support session_status, which Aura's
 * handler does.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Alaress\Session;
use Aura\Session;

class Factory {

    /**
     * @var array
     */
    protected $cookies;

    /**
     * Creates factory.
     *
     * @param array $cookies Cookies, often from $_COOKIE but not always.
     */
    public function __construct($cookies) {
        $this->cookies = $cookies;
    }

    /**
     * Creates session handler.
     *
     * @return Session\Session
     */
    public function build() {
        $segmentFactory   = new SegmentFactory();
        $csrfTokenFactory = new Session\CsrfTokenFactory(new Session\Randval(new Session\Phpfunc()));
        if (function_exists('session_status')) {
            return new Session\Session($segmentFactory, $csrfTokenFactory, $this->cookies);
        }
        else {
            return new Compat53($segmentFactory, $csrfTokenFactory, $this->cookies);
        }
    }

} 