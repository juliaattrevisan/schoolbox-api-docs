<?php

/**
 * Bulk_Abstract
 *
 * @author    Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
abstract class Bulk_Abstract
{
    protected $file = null;

    /**
     * Constructor
     *
     * @param string $file
     */
    public function __construct($file)
    {
        $this->setFile($file);
    }


    /* Getters/Setters
     */

    /**
     * Set file
     *
     * @param string $file
     */
    protected function setFile($file)
    {
        $this->file = $file;
    }

    /**
     * Get file
     *
     * @return string
     */
    protected function getFile()
    {
        return $this->file;
    }
}
