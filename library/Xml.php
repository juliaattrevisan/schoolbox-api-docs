<?php

use Alaress\Http\Cache;

/**
 * Alaress_Rss_Cache
 *
 * Originally written by Alex Tworowsky
 * Refactored/recomposed by moi
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
class Xml
{
    /**
     * Stores the XML, if loaded
     */
    public ?SimpleXMLElement $xml;

    /**
     * Constructor
     *
     * @param string $url   The URL to fetch XML from.
     * @param Cache  $cache The HTTP client cache wrapper to use to fetch `$source`.
     * @throws InvalidArgumentException
     */
    public function __construct(string $url, private Cache $cache)
    {
        if (empty($url)) {
            return;
        }

        // Load from cache
        try {
            $response = $this->cache->request($url);
        } catch (\GuzzleHttp\Exception\GuzzleException $e) {
            /**
             * Either requesting the RSS feed, or the RSS feed failed, error out here.
             * May have been a request or connection timeout.
             */
            return;
        }

        try {
            libxml_use_internal_errors(true);
            $this->xml = new SimpleXMLElement($response);
        } catch (Exception $e) {
            return;
        }
    }


    /**
     * Arbitrary get a property
     *
     * @param string $property
     * @return SimpleXMLElement|null
     */
    public function __get($property)
    {
        return $this->xml->$property ?? null;
    }
}
