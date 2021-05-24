<?php

/**
 * A session segmenter that allows access to $_SESSION directly.
 *
 * Calling Aura\Session\Session->newSegment(null) when the session's segment
 * factory creates instances of this class returns a segment that references
 * $_SESSION directly.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Alaress\Session;

use Aura\Session\Segment as BaseSegment;

class Segment extends BaseSegment
{

    /**
     * Overrides Aura's session segments to work with base $_SESSION.
     *
     * @return void
     */
    protected function load()
    {
        if (isset($this->name)) {
            parent::load();
        } else {
            if (!isset($_SESSION)) {
                $_SESSION = array();
            }
            // set $data as a reference to the $_SESSION key
            $this->data = &$_SESSION;
        }
    }
}
