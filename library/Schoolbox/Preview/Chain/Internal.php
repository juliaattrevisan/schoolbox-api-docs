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
use Schoolbox\Preview\Chain\Strategy\Internal\Media;
use Schoolbox\Preview\Chain\Strategy\Internal\Embed;
use Schoolbox\Preview\Chain\Strategy\Internal\Html;
use Schoolbox\Preview\Chain\Strategy\Internal\Redirect;

class Internal extends Base {

    /**
     * Constructor
     *
     * @param $url
     * @param $hash
     */
    public function __construct($url, $hash) {
        $this->start([
            new Image($url, $hash),
            new Media($url, $hash),
//            new Embed($url, $hash),
//            new Html($url, $hash),
//            new Redirect($url, $hash),
        ]);
    }
}