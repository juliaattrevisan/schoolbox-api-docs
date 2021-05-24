<?php

/**
 * Extension
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\File;

class Extension
{

    /**
     * Identifier
     *
     * @var null
     */
    private $id = null;

    /**
     * File extension
     *
     * @var string
     */
    private $extension = '';

    /**
     * Set extension
     *
     * @param string $extension
     */
    public function setExtension($extension)
    {
        $this->extension = $extension;
    }

    /**
     * Get extension
     *
     * @return string
     */
    public function getExtension()
    {
        return $this->extension;
    }

    /**
     * Set id
     *
     * @param null $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * Get id
     *
     * @return null
     */
    public function getId()
    {
        return $this->id;
    }
}
