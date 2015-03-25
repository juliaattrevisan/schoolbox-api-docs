<?php
/**
 * Base
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Preview\Chain\Strategy\External;

use Schoolbox\Preview\Chain\Strategy\Base as StrategyBase;

abstract class Base extends StrategyBase {

    /**
     * The requested url
     *
     * @var string
     */
    protected $url = '';

    /**
     * Header data from get_headers()
     *
     * @var array
     */
    protected $headers = array();

    /**
     * Constructor
     *
     * @param $url
     * @param $headers
     */
    public function __construct($url, $headers) {
        $this->setUrl($url);
        $this->setHeaders($headers);
    }

    /**
     * Macro method
     *
     * @return mixed
     */
    protected function findContentType() {
        return $this->findByKey('content-type');
    }

    /**
     * Get a header key
     *
     * @param $key
     * @return mixed
     */
    protected function findByKey($key) {
        $headers = $this->getHeaders();

        return $headers[$key];
    }

    /**
     * Set headers
     *
     * @param array $headers
     */
    protected function setHeaders($headers)
    {
        $this->headers = $headers;
    }

    /**
     * Get headers
     *
     * @return array
     */
    protected function getHeaders()
    {
        return $this->headers;
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
} 