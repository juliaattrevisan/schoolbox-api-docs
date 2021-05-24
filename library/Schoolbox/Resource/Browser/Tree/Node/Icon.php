<?php

/**
 * Icon
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Resource\Browser\Tree\Node;

class Icon
{

    /**
     * css class associated with this icon
     * (threePanel resource browser)
     *
     * @var string
     */
    private $css = 'binary';

    /**
     * Actual icon filename
     * (tinyMCE popup resource browser)
     *
     * @var string
     */
    private $file = 'binary.png';

    /**
     * Constructor
     */
    public function __construct()
    {
    }

    /* Getters/Setters
     */

    /**
     * Set css
     *
     * @param string $css
     */
    public function setCss($css)
    {
        $this->css = $css;
    }

    /**
     * Get css
     *
     * @return string
     */
    public function getCss()
    {
        return $this->css;
    }

    /**
     * Set file
     *
     * @param string $file
     */
    public function setFile($file)
    {
        $this->file = $file;
    }

    /**
     * Get file
     *
     * @return string
     */
    public function getFile()
    {
        return $this->file;
    }
}
