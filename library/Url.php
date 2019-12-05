<?php

/**
 * Formats urls into more meaningful stuff.
 *
 * Unit Tests: /test/unit/url/RelativeUrlTest.php
 *
 * Facilitates:
 * * determining if URL is local to the SB instance
 * * conversion from absolute to relative form
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
class Url
{
    /** @var string */
    private $url;

    /** @var string. The hostname according to config */
    private $siteHost;

    /**
     * Intentionally does not auto-prepend a protocol to otherwise valid absolute URLs.
     * This ensures caller retains control of protocol prepending.
     *
     * @param string $url
     * @param string|null $siteHost Override data-base configured siteHost. Used in tests or CLI context.
     * @throws InvalidArgumentException if $url is not a string
     * @throws DomainException If cannot determine the SB instance's configured hostname. This is required for determining relativity.
     */
    public function __construct($url, $siteHost = null)
    {
        if (!is_string($url)) {
            throw new InvalidArgumentException("URL must be a string. Type: " . gettype($url));
        }

        $this->setUrl(trim($url));

        // No override, use config
        if(null === $siteHost) {
            $site_hostname = trim(Config::get('site_hostname'));
            $site_domain = trim(Config::get('site_domain'));

            // Is domain part set ?
            if ($site_domain) {
                // Is host part set ?
                if ($site_hostname) {
                    $this->setSiteHost($site_hostname . '.' . $site_domain);
                } else {
                    $this->setSiteHost($site_domain);
                }
            } // Is host part set?
            else {
                if ($site_hostname) {
                    $this->setSiteHost($site_hostname);
                } // Unable to determine instance hostname
                else {
                    throw new DomainException('No hostname explicitly supplied, or retrievable from Database');
                }
            }
        } else {
            $this->setSiteHost(strval($siteHost));
        }
    }

    /**
     * If Local, convert to relative form.
     * If Not Local, ensure protocol is present.
     *
     * @throws InvalidArgumentException If unable to parse URL
     * @return static
     */
    public function normalise()
    {
        // This must go before isUrl() to ensure absolute but local URLs are converted to relative form.
        if ($this->isLocalUrl()) {
            $this->setUrl($this->getRelativeURL());
            return $this;
        }

        // Already a valid absolute URL ?
        if ($this->isUrl()) {
            return $this;
        }

        // Not valid relative, not valid absolute.

        // How about if we prepend a protocol ? Cache so we can restore original invalid value if required.
        $url = $this->getUrl();
        $this->prependScheme();

        // Is it now valid ?
        if ($this->isUrl()) {
            return $this;
        }

        // Restore original value, and throw
        $this->setUrl($url);
        throw new \InvalidArgumentException('Url appears invalid: ' . $url);
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
    public function getHostname()
    {
        $pattern = '/^(?:(?:[a-zA-Z]+:)?\/\/)?([a-zA-Z0-9.-]*)(?:[\/?#])?/';
        $matches = [];
        preg_match($pattern, $this->getUrl(), $matches);

        if(!count($matches)){
            return false;
        }

        // Check for zero-length labels, or labels that start or end with '-'

        $labels = explode('.', $matches[1]);
        foreach($labels as $label){
            if('' === $label && count($labels)){
                return false;
            }
            $subLabels = explode('-', $label);
            foreach($subLabels as $subLabel){
                if('' === $subLabel && count($subLabels)){
                    return false;
                }
            }
        }

        return $matches[1];
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

        // Unknown purpose. Commented out 2016/09/13.
        // TODO Remove after adequate elapse and nothing has broken.
//        $pattern[] = '/\+/';
//        $replace[] = 'p';

        return strtolower(preg_replace($pattern, $replace, $this->getUrl()));
    }

    /**
     * Is this a valid url?
     *
     * Note that this considers other-wise FQDN without a protocol
     * to be invalid, eg 'www.google.com/index.php' is NOT a Url
     *
     * @return bool
     */
    public function isUrl()
    {
        if (false === filter_var($this->getUrl(), FILTER_VALIDATE_URL)) {
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
            $this->setUrl(preg_replace('#^(//)?#', "$scheme://", $this->getUrl()));
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
    public function isLocalUrl()
    {
        // Relative URLs are always local
        if($this->isRelativeUrl()) {
            return true;
        }

        // Not relative, therefore need to check if hostnames match.

        /* Takes into account intranet name e.g. 'ilearn/' vs. 'ilearn.joeys.org/'
         * (that originally caused the issue with links not being correct)
         */

        // parse_url() does not perform as expected for URLs such as 'www.example.com'
        // In that case, it returns host='' + path='www.example.com'
        $urlHostname = $this->getHostname();

        // Collate all non-empty possible matches
        $localHostnames = [];
        if ($this->getSiteHost()) {
            $localHostnames[] = $this->getSiteHost();
        }
        if ($this->getServerHost()) {
            $localHostnames[] = $this->getServerHost();
        }

        // Does the URL match one of the 'local' hosts ?
        return in_array($urlHostname, $localHostnames);
    }

    /**
     * Returns true if url is an ftp link
     */
    public function isFtpUrl()
    {
        return in_array(parse_url($this->getUrl(), PHP_URL_SCHEME), ['ftp', 'ftps']);
    }

    /**
     * Get a relative url variant of $url (regardless of whether it is a local URL)
     *
     * eg 'ftp://www.google.com/path/to/file.ext' => '/path/to/file.ext'
     *        '//www.google.com/path/to/file.ext' => '/path/to/file.ext'
     *                        '/path/to/file.ext' => '/path/to/file.ext'
     *          'www.google.com'                  => '/'
     *          'www.google.com:8080'             => '/'
     *          ''                                => ''
     *
     * @return string
     */
    public function getRelativeURL()
    {
        if($this->isRelativeUrl()) {
            return $this->getUrl();
        }

        // Strip any protocol, hostname and port
        $pattern = '/^(?:(?:[a-zA-Z]+:)?\/\/)?(?:[a-zA-Z0-9.-]*(:[0-9]+)?(?:\/)?)/';
        return preg_replace($pattern, '/' , $this->getUrl());
    }

    /**
     * @return bool
     */
    public function isRelativeUrl()
    {
        // Is this a relative URL ? (empty, or starts with '#', '?' or single '/' - but not double '//')
        $pattern = '/^(()|(\/(?!\/)|[#?]).*)$/';
        if (preg_match($pattern, $this->getUrl())) {
            return true;
        }

        return false;
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
