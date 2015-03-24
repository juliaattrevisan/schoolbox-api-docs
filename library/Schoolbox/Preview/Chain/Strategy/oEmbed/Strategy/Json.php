<?php
/**
 * Json
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Preview\Chain\Strategy\oEmbed\Strategy;


class Json extends Base {

    /**
     * Search for element
     *
     * @return string
     */
    public function test() {
        $jsonResult = $this->findJsonEmbeddable($this->getXml());

        if (strlen($jsonResult)) {
            return "A JSON oEmbeddable video at {$jsonResult}";
        }
    }

    /**
     * Find first instance of oEmbed for json
     * (in case someone made multiple definitions somehow)
     *
     * @param $xml
     * @return mixed
     */
    private function findJsonEmbeddable($xml) {
        $query = "//link[@type='application/json+oembed']";

        return $this->findFirstMatching($xml, $query, 'href');
    }
}