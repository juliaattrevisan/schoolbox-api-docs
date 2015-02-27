<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Resource\Storage;


use League\Flysystem\File;
use Schoolbox\File\Mimetype;

class Model extends File
{

    /** @var string */
    protected $hash;

    /** @var Mimetype */
    protected $mimetype;

    /** @var int */
    protected $size;


    /**
     * @return string
     */
    public function getHash()
    {
        // calculate hash if not known
        if (!$this->hash) {
            $stream = $this->readStream();
            $hp     = hash_init('sha1');
            hash_update_stream($hp, $stream);
            $this->setHash(hash_final($hp));
        }
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
     * @return Mimetype
     */
    public function getMimetype()
    {
        return $this->mimetype;
    }


    /**
     * @param Mimetype $mimetype
     * @return self
     */
    public function setMimetype(Mimetype $mimetype)
    {
        $this->mimetype = $mimetype;
        return $this;
    }


    /**
     * @return int
     */
    public function getSize()
    {
        // calculate size if not known
        if ($this->size) {
            $this->setSize(parent::getSize());
        }
        return $this->size;
    }


    /**
     * @param int $size
     * @return self
     */
    public function setSize($size)
    {
        $this->size = $size;
        return $this;
    }

}
