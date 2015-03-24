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

use Schoolbox\Preview\Chain\Strategy\oEmbed\Start;

class OEmbed extends Base {

    /**
     * Test the oEmbed capability
     * http://www.oembed.com/
     *
     * @return string
     */
    public function test() {
        $curl = new \Alaress_Curl_Url($this->getUrl());
        $curl->exec();

        $data = $curl->getResult();

        /* Fire up Old Dominic to rummage in tag land
         */
        $document = new \DomDocument();
        libxml_use_internal_errors(true);

        $document->loadHTML($data);
        libxml_clear_errors();

        $xml = new \DOMXpath($document);

        /* oEmbed chain
         */
        $chain = new Start($xml);
        $result = $chain->getResult();

        if ($result !== false) {
            return $result;
        }

        /* No other oEmbed options, continue as normal
         */
        return $this->testNext();
    }
}