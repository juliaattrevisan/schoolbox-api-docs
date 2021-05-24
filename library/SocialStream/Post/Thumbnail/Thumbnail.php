<?php

/**
 * SocialStream_Post_Thumbnail_Abstract
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace SocialStream\Post\Thumbnail;

use Schoolbox\File\MimetypeMapper;
use SocialStream\Post\Player\Player;

/**
 * Class Thumbnail
 * @package SocialStream\Post\Thumbnail
 */
abstract class Thumbnail
{

    /**
     * XML output
     *
     * @var \DOMXPath|null
     */
    protected $xml = null;

    /**
     * The url to fetch
     *
     * @var string
     */
    protected $url = '';

    /**
     * Next class to deal with the problem
     *
     * @var Player|bool
     */
    protected $next = false;

    /**
     * An instance of FileType
     *
     * This checks whether a url contains a valid image or not
     * by checking file extension
     *
     * @var \FileType|null
     */
    protected $fileType = null;

    /**
     * The xpath query
     *
     * @var string
     */
    protected $query = '';

    /**
     * These are the attributes that might contain a link
     * to an image you want to retrieve
     *
     * @var array
     */
    protected $contentAttributes = [];

    /**
     * Constructor
     *
     * @param \DOMXPath|null $xml
     * @param string         $url
     */
    public function __construct($xml, $url)
    {
        $this->setXml($xml);
        $this->setUrl($url);
        $this->setFileType(new \FileType($url));
    }

    /**
     * Subclasses define how to fetch thumbnails
     *
     * @return mixed
     */
    public function fetchThumbnail()
    {
        $allElements = $this->getXml()->query($this->getQuery());

        foreach ($allElements as $currentElement) {
            foreach ($currentElement->attributes as $currentAttribute) {
                if (in_array($currentAttribute->nodeName, $this->getContentAttributes())) {
                    $match = trim($currentAttribute->nodeValue);
                    if (self::isImage($match)) {
                        /* Derive absolute url if the image url is actually
                         * specified relatively (www.google.com)
                         */
                        if ($match[0] == '/') {
                            $bits = parse_url($this->getUrl());
                            return 'http://' . $bits['host'] . $match;
                        } else {
                            return $match;
                        }
                    }
                }
            }
        }

        /* Otherwise get the next class to deal with it
         */
        if ($this->getNext()) {
            return $this->getNext()->fetchThumbnail();
        }

        return '';
    }

    /* Getters/Setters
     */

    /**
     * Set next
     *
     * @param Thumbnail|bool $next
     */
    public function setNext($next): void
    {
        $this->next = $next;
    }

    /**
     * Get next
     *
     * @return Thumbnail|bool
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
    protected function setUrl($url): void
    {
        $this->url = $url;
    }

    /**
     * Get url
     *
     * @return string
     */
    protected function getUrl(): string
    {
        return $this->url;
    }

    /**
     * Set file type
     *
     * @param \FileType|null $fileType
     */
    protected function setFileType($fileType): void
    {
        $this->fileType = $fileType;
    }

    /**
     * Get file type
     *
     * @return \FileType|null
     */
    protected function getFileType(): ?\FileType
    {
        return $this->fileType;
    }

    /**
     * Set xml
     *
     * @param \DOMXPath|null $xml
     */
    protected function setXml($xml): void
    {
        $this->xml = $xml;
    }

    /**
     * Get xml
     *
     * @return \DOMXPath|null
     */
    protected function getXml(): ?\DOMXPath
    {
        return $this->xml;
    }

    /**
     * Set content attributes
     *
     * @param array $contentAttributes
     */
    protected function setContentAttributes($contentAttributes): void
    {
        $this->contentAttributes = $contentAttributes;
    }

    /**
     * Get content attributes
     *
     * @return array
     */
    protected function getContentAttributes(): array
    {
        return $this->contentAttributes;
    }

    /**
     * Set query
     *
     * @param string $query
     */
    protected function setQuery($query): void
    {
        $this->query = $query;
    }

    /**
     * Get query
     *
     * @return string
     */
    protected function getQuery(): string
    {
        return $this->query;
    }

    /**
     * Checks whether the url is image
     * @param string $url
     * @return bool
     */
    protected static function isImage(string $url): bool
    {
        /* If it has at least the right extension, download it directly
         */
        if (($filename = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_BASENAME))) {
            try {
                $file = MimetypeMapper::getInstance()->getByFilename($filename);
                if ($file->isImage()) {
                    return true;
                }
            } catch (\Exception $e) {
            }
        }
        return false;
    }
}
