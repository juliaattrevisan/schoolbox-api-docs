<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Sync\Condition\Fragment;

/**
 * Class Fragment
 *
 * An Condition Fragment definition. The $filename is its twig template
 *
 * @package Schoolbox\Sync\Condition\Fragment
 */
class Fragment
{

    /**
     * Template filename
     *
     * @var string
     */
    private $filename;


    /**
     * Constructor
     *
     * @param string $filename
     */
    public function __construct($filename)
    {
        $this->filename = $filename;
    }

    /* Getters/Setters
     */

    /**
     * Get filename
     *
     * @return string
     */
    public function getFilename()
    {
        return $this->filename;
    }
}
