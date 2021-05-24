<?php

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
     * Stores the XML
     * @var SimpleXMLElement
     */
    public $xml;


    /**
     * @var Alaress_Rss_Cache
     */
    private $cache;


    /**
     * Constructor
     *
     * @param string $url
     * @throws InvalidArgumentException
     */
    public function __construct($url)
    {
        if (empty($url)) {
            return;
        }

        /* Set up cached file
         */
        $this->cache = new Alaress_Rss_Cache();

        $this->cache->init($url);

        /* Load from cached file
         * Delete the cached file if it can't be loaded correctly
         */
        try {
            $this->xml = $this->cache->load();
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
