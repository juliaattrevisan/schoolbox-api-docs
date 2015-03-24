<?php
/**
 * Embed
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Preview\Chain\Strategy;

use \Embera\Embera;

class Embed extends Base {

    /**
     * Test the oEmbed capability
     * http://www.oembed.com/
     *
     * @return string
     */
    public function test() {
        $embera = new Embera();
        $embedCode = $embera->autoEmbed($this->getUrl());

        /* If the url is the same as $embedCode, nothing to replace
         */
        if ($embedCode != $this->getUrl()) {
            return $embedCode;
        }

        return $this->testNext();
    }
} 