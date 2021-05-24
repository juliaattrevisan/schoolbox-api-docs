<?php

/**
 * Wraps a HTML fragment in a full HTML document.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Alaress\Html;

use Schoolbox\IConfigLoader;
use Scrub;

class Wrapper
{

    /**
     * The value of the title tag for the HTML document.
     *
     * @var string
     */
    protected $title;

    /**
     * The body contents of the HTML document.
     *
     * @var string
     */
    protected $body;

    /**
     * The base href to use for this document, if set.
     *
     * @var string
     */
    protected $baseHref;

    /**
     * Creates a new wrapper.
     *
     * @param string $title
     * @param string $body
     */
    public function __construct($title = '', $body = '')
    {
        $this->setTitle($title)->setBody($body);
    }

    /**
     * Outputs HTML.
     *
     * @return string
     */
    public function render()
    {
        $contents = <<<HTML
<html>
<head>
%s
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>%s</title>
</head>
<body>%s</body>
</html>
HTML
        ;
        $baseHref    = $this->getBaseHref();
        $baseHrefTag = '';
        if (strlen($baseHref) > 0) {
            $baseHrefTag = sprintf('<base href="%s" />', htmlspecialchars($baseHref));
        }
        return sprintf(
            $contents,
            $baseHrefTag,
            htmlspecialchars($this->getTitle()),
            Scrub::html($this->getBody())
        );
    }

    /**
     * Loads the base href from whatever is in the passed server vars.
     *
     * @param  array $serverVars Global vars from $_SERVER
     * @return $this
     */
    public function loadBaseHrefFromServerVars($serverVars)
    {
        $protocol = !empty($serverVars['HTTPS']) ? 'https' : 'http';
        $host     = $serverVars['HTTP_X_FORWARDED_HOST'] ?? $serverVars['HTTP_HOST'];
        $baseHref = "$protocol://$host";
        // Don't set port if it's a standard port
        if (!in_array($serverVars['SERVER_PORT'], array(80, 443))) {
            $baseHref .= ':' . $serverVars['SERVER_PORT'];
        }
        $this->setBaseHref($baseHref);
        return $this;
    }

    /**
     * Loads the base href from configuration.
     *
     * @param  IConfigLoader $configLoader A config loader object
     * @return $this
     */
    public function loadBaseHrefFromConfig(IConfigLoader $configLoader)
    {
        $host      = $configLoader->get('site_domain');
        $subdomain = $configLoader->get('site_hostname');
        $baseHref  = (strlen($subdomain) > 0) ? ($subdomain . '.' . $host) : $host;
        $this->setBaseHref($baseHref);
        return $this;
    }

    /**
     * @param  string $body
     * @return $this
     */
    public function setBody($body)
    {
        $this->body = $body;
        return $this;
    }

    /**
     * @return string
     */
    public function getBody()
    {
        return $this->body;
    }

    /**
     * @param  string $title
     * @return $this
     */
    public function setTitle($title)
    {
        $this->title = $title;
        return $this;
    }

    /**
     * @return string
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * @param  string $baseHref
     * @return $this
     */
    public function setBaseHref($baseHref)
    {
        $this->baseHref = $baseHref;
        return $this;
    }

    /**
     * @return string
     */
    public function getBaseHref()
    {
        return $this->baseHref;
    }
}
