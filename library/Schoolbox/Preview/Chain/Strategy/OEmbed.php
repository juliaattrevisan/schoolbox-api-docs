<?php
/**
 * OEmbed
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Preview\Chain\Strategy;


class OEmbed extends Base {

    /**
     * Test the oEmbed capability
     * http://www.oembed.com/
     *
     * @return string
     */
    public function test() {

//        $curl = new \Alaress_Curl_Url(

        new \Alaress_Probe($this->getUrl());
        new \Alaress_Probe($this->getHeaders());
        die();
        return $this->testNext();
    }
} 