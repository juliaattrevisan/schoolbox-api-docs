<?php
/**
 * Json
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Preview\Chain\Strategy\oEmbed;


class Xml extends Base {

    /**
     * Search for element
     *
     * @return string
     */
    public function test() {
        $xmlResult = $this->findXmlEmbeddable($this->getXml());

        if (strlen($xmlResult)) {
            return "An XML oEmbeddable video at {$xmlResult}";
        }
    }

    /**
     * Find first instance of oEmbed for xml
     * (in case someone made multiple definitions somehow)
     *
     * @param $xml
     * @return mixed
     */
    private function findXmlEmbeddable($xml) {
        $query = "//link[@type='text/xml+oembed']";

        return $this->findFirstMatching($xml, $query, 'href');
    }
}