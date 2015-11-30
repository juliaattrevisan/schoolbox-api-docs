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

use \Schoolbox\Collection;

class Fragment {

    /**
     * Template filename
     *
     * @var string
     */
    private $filename = '';

    /**
     * A Collection of ValueLabelInterface instances
     *
     * @var array
     */
    private $data = null;

    /**
     * Constructor
     *
     * @param $filename
     * @param Collection $data
     */
    public function __construct($filename, $data = null) {
        $this->setFilename($filename);

        if ($data instanceof Collection) {
            $this->setData($data);
        }
        else {
            $this->setData(new Collection());
        }
    }

    /* Getters/Setters
     */

    /**
     * Set data
     *
     * @param Collection $data
     */
    private function setData($data)
    {
        $this->data = $data;
    }

    /**
     * Get data
     *
     * @return Collection
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