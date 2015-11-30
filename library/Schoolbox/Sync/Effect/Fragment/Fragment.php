<?php
/**
 * Fragment
 *
 * An Effect Fragment definition. The $filename is its twig template
 * and $data is the key/value set which the twig template utilises
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Sync\Effect\Fragment;


class Fragment {

    /**
     * Template filename
     *
     * @var string
     */
    private $filename = '';

    /**
     * An array of key/value pairs
     *
     * @var array
     */
    private $data = [];

    /**
     * Constructor
     *
     * @param $filename
     * @param array $data
     */
    public function __construct($filename, $data = []) {
        $this->setFilename($filename);
        $this->setData($data);
    }

    /* Getters/Setters
     */

    /**
     * Set data
     *
     * @param array $data
     */
    private function setData($data)
    {
        $this->data = $data;
    }

    /**
     * Get data
     *
     * @return array
     */
    public function getData()
    {
        return $this->data;
    }

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