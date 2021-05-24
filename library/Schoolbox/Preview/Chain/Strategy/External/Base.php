<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Preview\Chain\Strategy\External;

use Schoolbox\Preview\Chain\Strategy\Base as StrategyBase;

/**
 * Class Base
 *
 * @package Schoolbox\Preview\Chain\Strategy\External
 */
abstract class Base extends StrategyBase
{

    /**
     * The requested url
     *
     * @var string
     */
    protected $url = '';

    /**
     * Config array containing options passed in to element
     *
     * @var array
     */
    protected $config = array();

    /**
     * Constructor
     *
     * @param string $url
     * @param array $config
     */
    public function __construct($url, $config)
    {
        $this->setUrl($url);
        $this->setConfig($config);
    }

    /**
     * Set config
     *
     * @param array $config
     */
    protected function setConfig($config)
    {
        $this->config = $config;
    }

    /**
     * Get config
     *
     * @return array
     */
    protected function getConfig()
    {
        return $this->config;
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
