<?php

/**
 * UrlDetection
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace SocialStream;

class UrlDetection
{

    /**
     * Urls used by Storage
     *
     * @var array
     */
    private $storagePatterns = array(
        '#^/cms/sendVideo\.php\?#',
        '#^/storage/fetch\.php\?#',
        '#^/storage/image\.php\?#',
    );

    /**
     * Urls used by the resource browser
     *
     * @var array
     */
    private $resourcePatterns = array(
        '#^/send\.php\?#',
    );

    /**
     * Homepage used by the resource browser. First capturing match contains the
     * homepage ID or code. (Assume only IDs for now)
     *
     * @var array
     */
    private $homepagePatterns = array(
        '#^/homepage/(\d+)#',
    );

    /**
     * Homepage used by the WebDAV browser: first capturing match contains the
     * ID of the file in WebDAV.
     *
     * @var array
     */
    private $webdavPatterns = ['#^/cms/sendNetworkFile.php\\?#'];

    /**
     * The url to analyse
     *
     * @var string
     */
    private $url = '';

    /**
     * Constructor
     */
    public function __construct($url)
    {
        $this->setUrl($url);
    }

    /**
     * Get the id value out of the given url, if provided
     */
    public function getParameter($parameter)
    {
        $bits = parse_url($this->getUrl());
        $query = $bits['query'];

        parse_str($query, $values);
        $val = $values[$parameter];

        return $val;
    }

    /**
     * Get the id value out of the provided url
     *
     * @return mixed
     */
    public function getId()
    {
        return $this->getParameter('id');
    }

    /**
     * Get the hash value out of the provided url
     */
    public function getHash()
    {
        return $this->getParameter('hash');
    }

    /**
     * Gets the homepage ID or code out of the URL for this page.
     *
     * @return string
     */
    public function getHomepageIdFromUrl()
    {
        foreach ($this->getHomepagePatterns() as $pattern) {
            if (preg_match($pattern, $this->getUrl(), $matches)) {
                return $matches[1];
            }
        }
        return null;
    }

    /**
     * Match $this->getUrl() against a pattern list (usually from
     * $this->get*Patterns())
     *
     * @param  array $patterns
     * @return bool
     */
    protected function matchAgainstPatterns($patterns)
    {
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $this->getUrl())) {
                return true;
            }
        }
        return false;
    }

    /**
     * Bypass caching for local urls
     *
     * @return bool
     */
    public function isLocalUrl()
    {

        /* A list of local streaming urls which are used to
         * bypass caching for Upload and Resource attachments
         */
        $allPatterns = array_merge(
            $this->getStoragePatterns(),
            $this->getResourcePatterns(),
            $this->getHomepagePatterns(),
            $this->webdavPatterns,
        );
        return $this->matchAgainstPatterns($allPatterns);
    }

    /**
     * Bypass caching for local urls
     *
     * @return bool
     */
    public function isStorageUrl()
    {
        return $this->matchAgainstPatterns($this->getStoragePatterns());
    }

    /**
     * Bypass caching for local urls
     *
     * @return bool
     */
    public function isResourceUrl()
    {
        return $this->matchAgainstPatterns($this->getResourcePatterns());
    }

    /**
     * Bypass caching for local urls
     *
     * @return bool
     */
    public function isHomepageUrl()
    {
        return $this->matchAgainstPatterns($this->getHomepagePatterns());
    }

    /**
     * Bypass caching for local urls
     *
     * @return bool
     */
    public function isWebdavUrl(): bool
    {
        return $this->matchAgainstPatterns($this->webdavPatterns);
    }

    /* Getters/Setters
     */

    /**
     * Set resource patterns
     *
     * @param array $resourcePatterns
     */
    private function setResourcePatterns($resourcePatterns)
    {
        $this->resourcePatterns = $resourcePatterns;
    }

    /**
     * Get resource patterns
     *
     * @return array
     */
    public function getResourcePatterns()
    {
        return $this->resourcePatterns;
    }

    /**
     * Set homepage patterns
     *
     * @param array $homepagePatterns
     */
    private function setHomepagePatterns($homepagePatterns)
    {
        $this->homepagePatterns = $homepagePatterns;
    }

    /**
     * Get homepage patterns
     *
     * @return array
     */
    public function getHomepagePatterns()
    {
        return $this->homepagePatterns;
    }

    /**
     * Set storage patterns
     *
     * @param array $storagePatterns
     */
    private function setStoragePatterns($storagePatterns)
    {
        $this->storagePatterns = $storagePatterns;
    }

    /**
     * Get storage patterns
     *
     * @return array
     */
    public function getStoragePatterns()
    {
        return $this->storagePatterns;
    }

    /**
     * Set url
     *
     * @param string $url
     */
    private function setUrl($url)
    {
        $this->url = $url;
    }

    /**
     * Get url
     *
     * @return string
     */
    private function getUrl()
    {
        return $this->url;
    }
}
