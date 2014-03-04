<?php
/**
 * A session segmenter that allows access to $_SESSION directly.
 *
 * Calling Aura\Session\Session->newSegment(null) when the session's segment
 * factory creates instances of this class returns a segment that references
 * $_SESSION directly.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Alaress\Session;
use Aura\Session\Segment as BaseSegment;

class Segment extends BaseSegment {

    /**
     * Forces a session start (or reactivation) and loads the segment data
     * from the session.
     *
     * @return void
     */
    protected function load() {
        // is data already loaded?
        if ($this->data !== null) {
            // no need to re-load
            return;
        }

        // if the session is not started, start it
        if (! $this->session->isStarted()) {
            $this->session->start();
        }

        // if we don't have a $_SESSION key for the segment, create one
        if (!isset($this->name)) {
            if (!isset($_SESSION)) {
                $_SESSION = array();
            }

            // set $data as a reference to the $_SESSION key
            $this->data = &$_SESSION;
        }
        else {
            if (! isset($_SESSION[$this->name])) {
                $_SESSION[$this->name] = array();
            }

            // set $data as a reference to the $_SESSION key
            $this->data = &$_SESSION[$this->name];
        }
    }

}
