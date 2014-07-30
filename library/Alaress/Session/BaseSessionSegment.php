<?php
/**
 * A session segmenter that accesses $_SESSION directly, instead of
 * $_SESSION[$this->name].
 *
 * Doesn't support flash vars.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Alaress\Session;
use Aura\Session as BaseSession;

class BaseSessionSegment extends BaseSession\Segment {

    /**
     * Constructor - doesn't take a name parameter, because it works on
     * $_SESSION directly.
     *
     * @param BaseSession\Session $session
     */
    public function __construct(BaseSession\Session $session) {
        $this->session = $session;
    }

    /**
     * Overrides Aura's session segments to work with base $_SESSION.
     *
     * @return void
     */
    protected function load() {
        if (!isset($_SESSION)) {
            $_SESSION = array();
        }
        // set $data as a reference to the $_SESSION key
        $this->data = &$_SESSION;
    }

}
