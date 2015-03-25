<?php
/**
 * Chain
 *
 * Seeks preview from an External (non-local) url
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Preview\Chain;

use Schoolbox\Preview\Chain\Strategy\Internal\Image;
use Schoolbox\Preview\Chain\Strategy\Internal\Embed;
use Schoolbox\Preview\Chain\Strategy\Internal\Html;
use Schoolbox\Preview\Chain\Strategy\Internal\Redirect;

class Internal extends Base {

    /**
     * Constructor
     *
     * @param $url
     */
    public function __construct($url) {
        $this->start([
            new Image(),
            new Embed(),
            new Html(),
            new Redirect(),
        ]);
    }
}