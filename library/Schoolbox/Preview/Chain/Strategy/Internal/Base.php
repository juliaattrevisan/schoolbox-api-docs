<?php
/**
 * Base
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Preview\Chain\Strategy\Internal;

use Schoolbox\Preview\Chain\Strategy\Base as StrategyBase;
use Schoolbox\Resource\Storage\Model as StorageModel;

abstract class Base extends StrategyBase {

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
     * @var null
     */
    protected $item = null;

    /**
     * Constructor
     *
     * @param $url
     * @param $item
     */
    public function __construct($url, $item) {
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
     * @param $item
     */
    protected function setItem($item)
    {
        $this->item = $item;
    }

    /**
     * Get item
     *
     * @return
     */
    protected function getItem()
    {
        return $this->item;
    }
}