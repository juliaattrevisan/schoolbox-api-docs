<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace SocialStream\Post\Metadata;

/**
 * Class Chain
 *
 * @package SocialStream\Post\Metadata
 */
class Chain
{
    /**
     * Resultant thumbnail url
     *
     * @var string
     */
    private $result = '';


    /**
     * Constructor
     *
     * @param \Alaress_Curl_Url $curl
     * @param string $url
     */
    public function __construct($curl, $url)
    {

        /* xml the curl result so we can find things better
         * This allows us to xpath our way around
         *
         * Still, improperly formed documents according to
         * simplexml or domdocument causes it to fatally die
         * Avoid it by using internal_errors(true)
         * http://stackoverflow.com/questions/6090667/php-domdocument-errors-warnings-on-html5-tags
         */
        if (($curl != null) && strlen($curl->getResult())) {
            $data = $curl->getResult();

            // Convert UTF-8 characters in the $data string to HTML entities.
            // This is done to prevent \DomDocument below from messing up character encoding.
            // References:
            //  * https://stackoverflow.com/questions/11309194/php-domdocument-failing-to-handle-utf-8-characters#answer-11310258
            //  * https://stackoverflow.com/questions/8218230/php-domdocument-loadhtml-not-encoding-utf-8-correctly#answer-8218649
            //  * https://stackoverflow.com/questions/35854535/better-explanation-of-convmap-in-mb-encode-numericentity
            // Simpler but deprecated version of the below line:
            //  * $data = mb_convert_encoding($data, 'HTML-ENTITIES', 'UTF-8');
            //  * The hex range used below ([0x80, 0x10FFFF]) defines UTF-8 characters that are not ASCII characters.
            //  * https://en.wikipedia.org/wiki/UTF-8
            $data = mb_encode_numericentity($data, [0x80, 0x10FFFF, 0, ~0], 'UTF-8');
        } else {
            /* Use a default xml-parser friendly version of empty string
             * in case someone links to a nonexistent file or something
             */
            $data = '<html></html>';
        }

        $document = new \DomDocument();
        libxml_use_internal_errors(true);

        $document->loadHTML($data);
        libxml_clear_errors();

        $xml = new \DOMXpath($document);

        /* Set up chain of responsibility
         */
        $opengraph = new OpenGraph($xml, $url);
        $metatag = new MetaTag($xml, $url);
        $null = new NullMetadata($xml, $url);

        /* Try until one of them matches
         */
        $metatag->setNext($null);
        $opengraph->setNext($metatag);

        $result = $opengraph->fetchMetadata();

        $this->setResult($result);
    }

    /* Getters/Setters
     */

    /**
     * Set result
     *
     * @param string $result
     */
    private function setResult($result)
    {
        $this->result = $result;
    }

    /**
     * @return string|OpenGraph
     */
    public function getResult()
    {
        return $this->result;
    }
}
