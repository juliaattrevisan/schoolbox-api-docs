<?php
/**
 * Base authorization method - contains a session object.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Alaress\Authorizer\Rule;
use Aura\Session\Session;

abstract class BaseRule implements IAuthorizationRule {

    /**
     * @var Session
     */
    protected $session;

    /**
     * @param  Session $session
     * @return $this
     */
    public function setSession($session) {
        $this->session = $session;
        return $this;
    }

    /**
     * @return Session
     */
    public function getSession() {
        return $this->session;
    }

}
