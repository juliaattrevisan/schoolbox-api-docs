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

        /* Prefer JSON first
         */
        $jsonResult = $this->findJsonEmbeddable($xml);

        if (strlen($jsonResult)) {
            return "A JSON oEmbeddable video at {$jsonResult}";
        }

        /* Then try the XML version
         */
        $xmlResult = $this->findXmlEmbeddable($xml);

        if (strlen($xmlResult)) {
            return "An XML oEmbeddable video at {$xmlResult}";
        }

        /* No other oEmbed options, continue as normal
         */
        return $this->testNext();
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

    /**
     * Find first element containing informatin about $attribute
     * matching $query
     *
     * @param $xml
     * @param $query
     * @param $attribute
     * @return mixed
     */
    private function findFirstMatching($xml, $query, $attribute) {
        return array_shift($this->analyse($xml, $query, $attribute));
    }

    /**
     * Have a think about $query and return whatever it found
     *
     * @param $xml
     * @param $query
     * @param $attribute
     * @return string
     */
    private function analyse($xml, $query, $attribute) {
        $allElements = $xml->query($query);

        $stack = [];

        foreach($allElements as $currentElement) {
            foreach($currentElement->attributes as $currentAttribute) {
                if ($currentAttribute->nodeName == $attribute) {
                    $stack[] = $currentAttribute->nodeValue;
                }
            }
        }

        return $stack;
    }
} 