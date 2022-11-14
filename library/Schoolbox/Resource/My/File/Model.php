<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Resource\My\File;

use Alaress_Date_Format;
use Schoolbox\ISoftDeletable;
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
class Model extends AbstractFile implements ISoftDeletable
{
    /** @var Alaress_Date_Format|null */
    protected ?Alaress_Date_Format $publishedAt = null;

    /**
     * @return Alaress_Date_Format|null
     */
    public function getPublishedAt(): ?Alaress_Date_Format
    {
        return $this->publishedAt;
    }

    /**
     * @param Alaress_Date_Format|null $publishedAt
     *
     * @return static
     */
    public function setPublishedAt(?Alaress_Date_Format $publishedAt): self
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
