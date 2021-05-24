<?php

/**
 * A session segmenter that accesses $_SESSION directly, instead of
 * $_SESSION[$this->name].
 *
 * Doesn't support flash vars.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Alaress\Session;

use Aura\Session as BaseSession;

class BaseSessionSegment extends BaseSession\Segment
{

    /**
     * Constructor - doesn't take a name parameter, because it works on
     * $_SESSION directly.
     *
     * @param BaseSession\Session $session
     */
    public function __construct(BaseSession\Session $session)
    {
        $this->session = $session;
    }

    /**
     * Overrides Aura's session segments to work with base $_SESSION.
     *
     * {@inheritdoc}
     */
    protected function load()
    {
        if (!isset($_SESSION)) {
            $_SESSION = array();
        }
    }

    /**
     * Copied from base implementation to work with base $_SESSION.
     *
     * {@inheritdoc}
     */
    public function get($key, $alt = null)
    {
        $this->resumeSession();
        return isset($_SESSION[$key]) ? $_SESSION[$key] : $alt;
    }

    /**
     * Copied from base implementation to work with base $_SESSION.
     *
     * {@inheritdoc}
     */
    public function set($key, $val)
    {
        $this->resumeOrStartSession();
        $_SESSION[$key] = $val;
    }

    /**
     * Copied from base implementation to work with base $_SESSION.
     *
     * {@inheritdoc}
     */
    public function clear()
    {
        if ($this->resumeSession()) {
            $_SESSION = array();
        }
    }
}
