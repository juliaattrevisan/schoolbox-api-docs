<?php

/**
 * Formats urls into more meaningful stuff.
 *
 * Facilitates:
 * * determining if URL is local to the SB instance
 * * conversion from absolute to relative form
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Url
{
    /** @var string */
    private $url;

    /** @var string. The hostname according to config */
    private $siteHost;

    /**
     * @param string $url
     * @param string $siteHost Optional override to decouples the class for unit testing of 'relative' URLs
     * @throws InvalidArgumentException If cannot parse URL
     * @throws DomainException If cannot determine the SB instance's hostname
     */
    public function __construct($url, $siteHost = '')
    {
        $this->setUrl(trim($url));

        // Did caller override the site's hostname ?
        if ($siteHost) {
            $this->setSiteHost($siteHost);
        } else {
            // TODO - Uncomment on conversion to PHP7. Unable to catch till then.
//            try {
                $site_hostname = trim(Cfg::get('site_hostname'));
                $site_domain = trim(Cfg::get('site_domain'));
//            }
//            catch (Error $e){
//                $site_hostname = '';
//                $site_domain = '';
//            }

            // Is domain part set ?
            if ($site_domain) {
                // Is host part set ?
                if ($site_hostname) {
                    $this->setSiteHost($site_domain.'.'.$site_hostname);
                } else {
                    $this->setSiteHost($site_domain);
                }
            }
            // Is host part set?
            else if($site_hostname) {
                $this->setSiteHost($site_hostname);
            }
            // Unable to determine instance hostname
            else {
                throw new DomainException('No hostname defined in DB or explicitly set');
            }
        }

        /*
         * Ensure the passed URL is parsable
         */

        // Is this not a local or absolute URL ?
        if (!($this->isLocalURL() || $this->isUrl())) {
            // How 'bout if we prepend a scheme?
            $this->prependScheme();
            if (!$this->isUrl()) {
                throw new InvalidArgumentException('Unable to parse URL: ' . $url);
            }
            // Restore passed-in value. Caller must prepend protocol explicitly
            $this->setUrl($url);
        }
    }


    /**
     * Returns, eg, the 'www.example.com' from:
     *
     * * 'protocol://www.example.com'
     * * '//www.example.com'
     * * 'www.example.com'
     * * 'www.example.com/path/to/file.ext'
     * * 'www.example.com:8080'
     *
     * Note that this strips off port as well, eg 'localhost:8080/path/to/file.ext' => 'localhost'
     * Returns FALSE if not found
     *
     * @return string|false
     */
    public function parseHostname()
    {
        $pattern = '/^(?:(?:[a-zA-Z]+:)?\/\/)?([a-zA-Z0-9.-]*)/';        // (?:noCapture)
        $matches = [];
        preg_match($pattern, $this->getUrl(), $matches);
        return count($matches) > 1 ? $matches[1] : false;
    }


    /**
     * Creates slugs from a string.
     * Not sure what the third replacement is supposed to do
     *
     * @return string
     */
    public function getSlug()
    {
        $pattern[] = '/[^a-zA-Z\d\s\-\+\/]*/';
        $replace[] = '';

        $pattern[] = '/(\s|-|\/)+/';
        $replace[] = '-';

        $pattern[] = '/\+/';
        $replace[] = 'p';

        return strtolower(preg_replace($pattern, $replace, $this->getUrl()));
    }

    /**
     * Is this a valid url?
     *
     * @return bool
     */
    public function isUrl()
    {
        if(false === filter_var($this->getUrl(), FILTER_VALIDATE_URL)){
            return false;
        }
        return true;
    }

    /**
     * Prepend a scheme if one wasn't found
     * @param string $scheme
     */
    public function prependScheme($scheme = 'http')
    {
        $parsedScheme = parse_url($this->getUrl(), PHP_URL_SCHEME);

        if (!$parsedScheme) {
            // Strip any leading double '//'
            $url = preg_replace('|^//|', '', $this->getUrl());
            $this->setUrl("$scheme://$url");
        }
    }

    /**
     * Returns true if the URL is:
     * * a relative URL, or
     * * a local page according to DB config, or
     * * a local page according to $_SERVER['HTTP_HOST']. (When a school's intranet hostname does not match DB config)
     *
     * @return bool
     */
    public function isLocalURL()
    {
        // Empty URL implies relatively equal to current path
        if(0 === strlen($this->getUrl())){
            return true;
        }

        // Is this a relative URL ? (empty, or starts with '#', '?' or single '/' - but not double '//')
        $pattern = '/^(\/(?!\/)|[#?])/';
        if(preg_match($pattern, $this->getUrl())){
            return true;
        }

        // parse_url() does not perform as expected for URLs such as 'www.example.com'
        // In that case, it returns host='' + path='www.example.com'
        $urlHostname = $this->parseHostname();

        // Collate all non-empty possible matches
        $localHostnames = [
            $this->getSiteHost(),
        ];

        if($this->getServerHost()){
            $localHostnames[] = $this->getServerHost();
        }

        // Does the URL match one of the 'local' hosts ?
        if(in_array($urlHostname, $localHostnames)){
            return true;
        }

       return false;
    }

    /**
     * Returns true if url is an ftp link
     */
    public function isFTPURL()
    {
        return parse_url($this->getUrl(), PHP_URL_SCHEME) == 'ftp';
    }

    /**
     * Get a relative url variant of $url, regardless of whether it is a local URL
     *
     * eg 'ftp://www.google.com/path/to/file.ext' => '/path/to/file.ext'
     *        '//www.google.com/path/to/file.ext' => '/path/to/file.ext'
     *                        '/path/to/file.ext' => '/path/to/file.ext'
     *
     * @return string
     */
    public function getRelativeURL()
    {
        /* Takes into account intranet name e.g. 'ilearn/' vs. 'ilearn.joeys.org/'
         * (that originally caused the issue with links not being correct)
         */

        // Strip any protocol and hostname
        $pattern = '/^(?:(?:[a-zA-Z]+:)?\/\/)?(?:[a-zA-Z0-9.-]*)/';
        return preg_replace($pattern, '', $this->getUrl());
    }

    /**
     * Get a redirect link with base64 encoded url
     *
     * @return string
     */
    public function getBase64EncodedRedirect()
    {
        return '/redir.php?url=' . urlencode(base64_encode($this->getUrl()));
    }

    /* Getters/Setters
      */

    /**
     * @return string
     */
    public function getUrl()
    {
        return $this->url;
    }

    /**
     * @param string $url
     * @return static
     */
    protected function setUrl($url)
    {
        $this->url = $url;
        return $this;
    }


    /**
     * @return string
     */
    protected function getSiteHost()
    {
        return $this->siteHost;
    }

    /**
     * @param string $siteHost
     * @return static
     */
    protected function setSiteHost($siteHost)
    {
        $this->siteHost = $siteHost;
        return $this;
    }

    /**
     * @return string
     */
    protected function getServerHost()
    {
        return isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
    }
}