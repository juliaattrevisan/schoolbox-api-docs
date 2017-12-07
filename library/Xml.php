<?php
/**
 * Alaress_Rss_Cache
 *
 * Originally written by Alex Tworowsky
 * Refactored/recomposed by moi
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Xml {
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
	 * @param  string $url
	 */
    public function __construct($url) {
		if (empty($url)) {
			return;
		}


		/* Set up cached file
		 */
		$this->setCache(new Alaress_Rss_Cache());

	    $this->getCache()->init($url);

		/* Load from cached file
		 * Delete the cached file if it can't be loaded correctly
		 */
        try {
            $this->setXml($this->getCache()->load());
        }
		catch (Exception $e) {
            return;
        }
    }

	/**
	 * Set xml
	 *
	 * @param  SimpleXMLElement $val
	 * @return void
	 */
	private function setXml(SimpleXMLElement $val) {
		$this->xml = $val;
	}

	/**
	 * Set cached file
	 *
	 * @param  $val
	 * @return void
	 */
	private function setCache($val) {
		$this->cache = $val;
	}

	/**
	 * Get cached file
	 *
	 * @return null
	 */
	private function getCache() {
		return $this->cache;
	}

	/**
	 * Arbitrary get a property
	 *
	 * @param  $property
	 * @return
	 */
    public function __get($property) {
        if (isset($this->xml->$property)) {
            return $this->xml->$property;
        }
    }
}

