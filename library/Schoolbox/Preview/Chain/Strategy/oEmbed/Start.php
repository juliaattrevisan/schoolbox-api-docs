<?php
/**
 * Start
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Preview\Chain\Strategy\oEmbed;

use Schoolbox\Preview\Chain\Base as ParentChain;
use Schoolbox\Preview\Chain\Strategy\oEmbed\Strategy\Json;
use Schoolbox\Preview\Chain\Strategy\oEmbed\Strategy\Xml;
use Schoolbox\Preview\Chain\Strategy\oEmbed\Strategy\Null;

class Start extends ParentChain {

    /**
     * Constructor
     *
     * @param $xml
     */
    public function __construct($xml) {
        $this->start([
            new Json($xml),
            new Xml($xml),
            new Null($xml)
        ]);
    }
} 