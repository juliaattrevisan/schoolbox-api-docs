<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace SocialStream\Post\Metadata;

/**
 * Class Metadata
 *
 * @package SocialStream\Post\Metadata
 */
abstract class Metadata
{

    /**
     * XML output from curl
     *
     * @var \DOMXpath
     */
    protected $xml;


    /**
     * Url title
     *
     * @var string
     */
    protected $title = '';


    /**
     * Url description
     *
     * @var string
     */
    protected $description = '';


    /**
     * The url to fetch
     *
     * @var string
     */
    protected $url = '';


    /**
     * Next class to deal with the problem
     *
     * @var Metadata|null
     */
    protected $next;


    /**
     * Constructor
     *
     * @param \DOMXpath $xml
     * @param string $url
     */
    public function __construct($xml, $url)
    {
        $this->setXml($xml);
        $this->setUrl($url);
    }


    /**
     * Subclasses define how to fetch thumbnails
     *
     * @return Metadata
     */
    abstract public function fetchMetadata();

    /**
     * Have a think about $query and return whatever it found
     *
     * @param string $query
     * @param array $allowableAttributes
     * @return string|null
     */
    protected function analyse($query, $allowableAttributes)
    {
        $allElements = $this->getXml()->query($query);

        foreach ($allElements as $currentElement) {
            foreach ($currentElement->attributes as $currentAttribute) {
                if (in_array($currentAttribute->nodeName, $allowableAttributes)) {
                    return trim($currentAttribute->nodeValue);
                }
            }
        }

        return null;
    }

    /* Getters/Setters
     */

    /**
     * Set next
     *
     * @param Metadata $next
     */
    public function setNext($next)
    {
        $this->next = $next;
    }

    /**
     * Get next
     *
     * @return Metadata|null
     */
    public function getNext()
    {
        return $this->next;
    }

    /**
     * Set url
     *
     * @param string $url
     */
    protected function setUrl($url)
    {
        $this->url = $url;
    }

    /**
     * Get url
     *
     * @return string
     */
    protected function getUrl()
    {
        return $this->url;
    }

    /**
     * Set description
     *
     * @param string $description
     */
    protected function setDescription($description)
    {
        $this->description = $description;
    }

    /**
     * Get description
     *
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * Set title
     *
     * @param string $title
     */
    protected function setTitle($title)
    {
        $this->title = $title;
    }

    /**
     * Get title
     *
     * @return string
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * Set xml
     *
     * @param \DOMXpath $xml
     */
    protected function setXml($xml)
    {
        $this->xml = $xml;
    }

    /**
     * Get xml
     *
     * @return \DOMXpath
     */
    protected function getXml()
    {
        return $this->xml;
    }
}
