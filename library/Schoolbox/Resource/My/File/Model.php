<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Resource\My\File;

use Alaress_Date_Format;
use Schoolbox\Resource;
use Schoolbox\Resource\AbstractFile;

/**
 * Class Model
 *
 * The MyFile Model. Represents a file within the portfolio system.
 *
 * @package Schoolbox\Resource\My\File
 * @method Resource\My\Folder\Model getFolder()
 */
class Model extends AbstractFile
{
    /** @var Alaress_Date_Format */
    protected $publishedAt;

    /**
     * Temporary fix to prevent the My Files file browser from dying.
     *
     * Twig calls `modifiedAt` on an instance of this class, which should call
     * this method. However, there's a `__call` on the parent class, and it
     * attempts to call `modifiedAt` on the internal storage object: that method
     * doesn't exist.
     *
     * @return null
     */
    public function getModifiedAt()
    {
        return null;
    }

    /**
     * @return Alaress_Date_Format
     */
    public function getPublishedAt()
    {
        return $this->publishedAt;
    }


    /**
     * @param Alaress_Date_Format $publishedAt
     * @return self
     */
    public function setPublishedAt($publishedAt)
    {
        $this->publishedAt = $publishedAt;
        return $this;
    }

    public function isPublished()
    {
        return $this->getPublishedAt() instanceof Alaress_Date_Format
            && $this->getPublishedAt()->getUnixTimeStamp() < Alaress_Date_Format::now()->getUnixTimeStamp();
    }

}
