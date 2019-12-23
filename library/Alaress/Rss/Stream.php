<?php

/**
 * Alaress_Rss_Stream
 *
 * Streams an RSS url. Can detect whether it's ATOM or RSS 2.0
 * specification, and chooses a Strategy accordingly
 *
 * The Alaress_Rss_Stream constructor takes a source url, but it
 * can be overridden by using setSource() after instantiation
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
class Alaress_Rss_Stream
{
    /**
     * URL of RSS feed to fetch
     * @var string
     */
    private $source;

    /**
     * Limit of RSS items to fetch
     * @var int
     */
    private $limit = 6;

    /**
     * Contains all feed items
     * @var array
     */
    private $output = [];

    /** This can be dynamically chosen based on structure
     * of input data. Choose strategy in the setup
     *
     * @param Alaress_Rss_Strategy_Interface
     */
    private $rssStrategy;

    /**
     * @var Xml
     */
    private $input;

    /**
     * Constructor
     *
     * Optionally takes a source url as an input. You can override
     * this by using setSource() after instantiation
     *
     * @param null|string $source
     */
    public function __construct($source = null)
    {
        if ($source !== null) {
            $this->setSource($source);
        }
    }

    /**
     * Configure the class which manages interpretation of RSS feeds
     *
     * @return bool
     */
    public function setup()
    {
        if ($this->getSource() != null) {

            /* The Xml class already uses caching
             */
            $this->setInput(new Xml($this->getSource()));

            /* If there was data, try to interpret it
             */
            if (count($this->getInput()->xml)) {

                /* Decide if the feed is an ATOM feed or a regular RSS 2.0
                 */
                if ($this->isInputAtom()) {
                    $this->setRssStrategy(new Alaress_Rss_Strategy_Atom());
                } else {
                    $this->setRssStrategy(new Alaress_Rss_Strategy_Rss20());
                }
            }
        }
        return false;
    }

    /**
     * Decide if the feed is an ATOM feed
     *
     * @return bool
     */
    private function isInputAtom()
    {

        foreach ($this->getInput()->xml->getNamespaces() as $namespace) {
            if ('http://www.w3.org/2005/Atom' === $namespace) {
                return true;
            }
        }

        foreach ($this->getInput()->xml->link as $currentLink) {
            if (false !== stripos($currentLink->attributes()->type, 'atom+xml')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get an RSS stream if a source was provided
     *
     */
    public function stream()
    {
        if ($this->getSource() != null) {
            $this->setup();

            /* If a strategy was devised, stream it
             */
            if ($this->getRssStrategy() != null) {
                $this->setOutput($this->getRssStrategy()->get($this->getInput(), $this->getLimit()));
            }
        }
    }

    /**
     * Get an RSS stream title
     *
     * @return string
     */
    public function getTitle()
    {
        if (($this->getSource() != null) && ($this->getRssStrategy() != null)) {
            return $this->getRssStrategy()->getTitle($this->getInput());
        }

        return 'Please enter a feed URL';
    }

    /**
     * Get an RSS stream link
     *
     * @return string
     */
    public function getLink()
    {
        if (($this->getSource() != null) && ($this->getRssStrategy() != null)) {
            return $this->getRssStrategy()->getLink($this->getInput());
        }

        return '';
    }

    /* Getters/Setters
     */

    /**
     * Set output contents
     *
     * @param  $val
     */
    private function setOutput($val)
    {
        $this->output = $val;
    }

    /**
     * Get output contents
     *
     * @return array
     */
    public function getOutput()
    {
        return $this->output;
    }

    /**
     * Set RSS handling class
     *
     * @param Alaress_Rss_Strategy_Interface $val
     */
    private function setRssStrategy(Alaress_Rss_Strategy_Interface $val)
    {
        $this->rssStrategy = $val;
    }

    /**
     * Get RSS handling class
     *
     * @return Alaress_Rss_Strategy_Interface
     */
    private function getRssStrategy()
    {
        return $this->rssStrategy;
    }

    /**
     * Set the limit
     *
     * @param int $val
     */
    public function setLimit($val)
    {
        $this->limit = $val;
    }

    /**
     * Get the limit
     *
     * @return int
     */
    private function getLimit()
    {
        return $this->limit;
    }

    /**
     * Set source url
     *
     * Silently enforce http:// protocol
     *
     * @param string $val
     */
    public function setSource($val)
    {
        $source = str_replace('feed://', 'http://', $val);

        $this->source = $source;
    }

    /**
     * Get source url
     *
     * @return string
     */
    private function getSource()
    {
        return $this->source;
    }

    /**
     * Set the contents of the input rss stream
     *
     * @param \Xml $val
     */
    private function setInput(\Xml $val)
    {
        $this->input = $val;
    }

    /**
     * Get the contents of the input rss stream
     *
     * @return \Xml
     */
    private function getInput()
    {
        return $this->input;
    }
}
