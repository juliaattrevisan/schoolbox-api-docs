<?php
/**
 * File
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Learning\Assessment\DueWork\Submission\File;


class Model
{

    /**
     * @var int
     */
    protected $id;

    /**
     * A crococococodoc UUID value which identifies the file
     * that was uploaded to crococococdoc
     *
     * @var string
     */
    protected $crocodocId;

    /**
     * The hash of the file's contents
     *
     * @var string
     */
    protected $hash;

    /**
     * The filename which was submitted
     *
     * @var string
     */
    protected $filename;

    /**
     * File constructor.
     */
    public function __construct()
    {
    }

    /* Getters/Setters
     */

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param int $id
     * @return self
     */
    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @return string
     */
    public function getCrocodocId()
    {
        return $this->crocodocId;
    }

    /* Getters/Setters
     */

    /**
     * @param string $crocodocId
     * @return self
     */
    public function setCrocodocId($crocodocId)
    {
        $this->crocodocId = $crocodocId;
        return $this;
    }

    /**
     * @return string
     */
    public function getHash()
    {
        return $this->hash;
    }

    /**
     * @param string $hash
     * @return self
     */
    public function setHash($hash)
    {
        $this->hash = $hash;
        return $this;
    }

    /**
     * @return string
     */
    public function getFilename()
    {
        return $this->filename;
    }

    /**
     * @param string $filename
     * @return self
     */
    public function setFilename($filename)
    {
        $this->filename = $filename;
        return $this;
    }
}