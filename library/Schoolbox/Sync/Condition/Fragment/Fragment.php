<?php
/**
 * Fragment
 *
 * An Condition Fragment definition. The $filename is its twig template
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Sync\Condition\Fragment;

class Fragment {

    /**
     * Template filename
     *
     * @var string
     */
    private $filename = '';

    /**
     * Constructor
     *
     * @param $filename
     */
    public function __construct($filename) {
        $this->setFilename($filename);
    }

    /* Getters/Setters
     */

    /**
     * Set filename
     *
     * @param string $filename
     */
    private function setFilename($filename)
    {
        $this->filename = $filename;
    }

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