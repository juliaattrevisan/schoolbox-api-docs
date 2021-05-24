<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Preview\Chain\Strategy\Internal;

use Schoolbox\Preview\Chain\Strategy\Base as StrategyBase;
use Schoolbox\Resource\File;
use Schoolbox\Resource\Storage;

/**
 * Class Base
 *
 * @package Schoolbox\Preview\Chain\Strategy\Internal
 */
abstract class Base extends StrategyBase
{

    /**
     * The url to test
     *
     * @var string
     */
    protected $url = '';


    /**
     * A storage model to test
     * A link model to test
     *
     * @var Storage\Model|File\Model
     */
    protected $item;


    /**
     * Constructor
     *
     * @param string $url
     * @param Storage\Model|File\Model $item
     */
    public function __construct($url, $item)
    {
        $this->setUrl($url);
        $this->setItem($item);
    }


    /* Getters/Setters
     */

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
     * Set item
     *
     * @param Storage\Model|File\Model $item
     */
    protected function setItem($item)
    {
        $this->item = $item;
    }


    /**
     * Get item
     *
     * @return Storage\Model|File\Model
     */
    protected function getItem()
    {
        return $this->item;
    }
}
