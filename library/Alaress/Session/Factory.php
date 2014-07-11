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
use Aura\Session as SessionLibrary;
use Aura\Session\Phpfunc;

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
     * @return Session
     */
    public function build() {
        $segmentFactory   = new SegmentFactory();
        $csrfTokenFactory = new SessionLibrary\CsrfTokenFactory(new SessionLibrary\Randval(new SessionLibrary\Phpfunc()));
        $func             = new Phpfunc();
        return new Session(
            $segmentFactory,
            $csrfTokenFactory,
            $func,
            $this->cookies
        );
    }
}
