<?php
/**
 * Chain
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Preview\Chain;

use Schoolbox\Preview\Chain\Strategy\Image;
use Schoolbox\Preview\Chain\Strategy\Other;

class Start extends Base {

    /**
     * Constructor
     *
     * @param $url
     */
    public function __construct($url) {
        $headers = 'le goog';

        $this->start([
            new Image($headers),
            new Other($headers),
        ]);
    }
}