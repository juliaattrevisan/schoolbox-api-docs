<?php

/**
 * Url
 *
 * Formats urls into more meaningful stuff.
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

    /** @var bool */
    private $useCfg;

    /** @var string */
    private $siteHostname;

    /** @var string */
    private $siteDomain;

    /** @var string */
    private $serverHost;


    /**
     * @param $url
     * @param bool $useCfg Decouples the class for unit testing
     * @throws InvalidArgumentException If URL cannot be parsed at all
     */
    public function __construct($url, $useCfg = true)
    {
        $this->setUrl(trim($url));

        $this->setUseCfg($useCfg);

        if ($this->getUseCfg()) {
            $this->setSiteHostname(Cfg::get('site_hostname'));
            $this->setSiteDomain(Cfg::get('site_domain'));

            if (isset($_SERVER['HTTP_HOST'])) {
                $this->setServerHost($_SERVER['HTTP_HOST']);
            }
        }

        // Ensure URL is parsable
        // Is this not a local or absolute URL ?
        if (!($this->isLocalURL() || $this->isUrl())) {
            // How 'bout if we prepend a scheme?
            $this->prependScheme();
            if (!$this->isUrl()) {
                throw new InvalidArgumentException('Unable to parse URL: ' . $url);
            }
        }
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
        $result = filter_var($this->getUrl(), FILTER_VALIDATE_URL);

        if ($result) {
            return true;
        }

        return false;
    }

    /**
     * Prepend a scheme if one wasn't found
     */
    public function prependScheme()
    {
        $scheme = parse_url($this->getUrl(), PHP_URL_SCHEME);

        if (!$scheme) {
            $this->setUrl('http://' . $this->getUrl());
        }
    }

    /**
     * Returns true if the url is for a local page
     * @return bool
     */
    public function isLocalURL()
    {
        if ($this->getServerHost()) {
            list($hostname, $domain) = explode('.', $this->getServerHost(), 2);
        } else {
            $hostname = $this->getSiteHostname();
        }

        // get the first character of the url if it's already a local link
        $lead = substr($this->getUrl(), 0, 1);
        if (in_array($lead, array("/", "#", "?"))) {
            return true;
        }

        $urlParts = parse_url($this->getUrl());

        if (in_array($urlParts['host'],
            array(
                $hostname,
                $this->getServerHost(),
                $this->getSiteHostname(),
                $this->getSiteHostname() . '.' . $this->getSiteDomain()
            )
        )) {
            return true;
        }


        return false;
    }

    /**
     * Returns true if url is an ftp link
     */
    public function isFTPURL()
    {
        if (parse_url($this->getUrl(), PHP_URL_SCHEME) == 'ftp') {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Get a relative url variant of $url
     *
     * @return mixed
     */
    public function getRelativeURL()
    {
        /* Takes into account subdomain name e.g. ilearn/ and ilearn.joeys
         * (that originally caused the issue with links not being correct)
         */
        $hosts = array(
            'http://' . $this->getSiteHostname() . '.' . $this->getSiteDomain(),
            'https://' . $this->getSiteHostname() . '.' . $this->getSiteDomain(),
            'http://' . $this->getSiteHostname(),
            'https://' . $this->getSiteHostname(),
        );

        if ($this->getServerHost()) {
            $hosts[] = 'http://' . $this->getServerHost();
            $hosts[] = 'https://' . $this->getServerHost();
        }

        return str_replace($hosts, '', $this->getUrl());
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

    /**
     * Returns whether the value is a HTTP, HTTPS, FTP or relative URL.
     *
     * This regex semantics should match the globally-available custom jquery validation
     * method 'isURL' defined in jquery.validate.extensions.js
     *
     * @return bool
     */
    public function isValidUrl()
    {
        // If prefixed with a scheme, domain name must have 2 or more levels with chars from [\w-]
        // If relative (prefixed with '/') remaining chars cannot have embedded spaces
        // Absolute URLs can have any non-whitespace chars in their path (this is not strictly valid)
        // TODO. This could be made more complex to filter more invalid paths, at the moment it's quite permissive.

        $regExp = '#^((((http)|(ftp))s?://[\w-]+(\.[\w-]+)+(/\S*)?)|(/\S*))$#';

        return preg_match($regExp, $this->url);
    }

    /* Getters/Setters
      */

    /**
     * Set useCfg. This determines whether to use the Cfg object, or
     * alternatively whatever input params you want
     *
     * @param bool $val
     * @return void
     */
    public function setUseCfg($val)
    {
        $this->useCfg = $val;
    }

    /**
     * Get useCfg
     *
     * @return bool
     */
    private function getUseCfg()
    {
        return $this->useCfg;
    }

    /**
     * Set url to work on
     *
     * @param string $val
     * @return void
     */
    private function setUrl($val)
    {
        $this->url = $val;
    }

    /**
     * Get url
     *
     * @return string
     */
    public function getUrl()
    {
        return $this->url;
    }

    /**
     * Set site hostname
     *
     * @param string $val
     * @return void
     */
    public function setSiteHostname($val)
    {
        $this->siteHostname = $val;
    }

    /**
     * Get site hostname
     *
     * @return string
     */
    private function getSiteHostname()
    {
        return $this->siteHostname;
    }

    /**
     * Set site domain
     *
     * @param string $val
     * @return void
     */
    public function setSiteDomain($val)
    {
        $this->siteDomain = $val;
    }

    /**
     * Get site domain
     *
     * @return string
     */
    private function getSiteDomain()
    {
        return $this->siteDomain;
    }

    /**
     * Set server host ($_SERVER['HTTP_HOST'])
     * @param string $val
     * @return void
     */
    public function setServerHost($val)
    {
        $this->serverHost = $val;
    }

    /**
     * Get server host
     *
     * @return string
     */
    private function getServerHost()
    {
        return $this->serverHost;
    }
}