<?php

/**
 * Model for normal (non-eportfolio) images
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Blog\Homepage\Image;

use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Mapper\Traits\Model\UpdatedAtDate;
use Schoolbox\Resource\File\StorableTrait;
use Schoolbox\Resource\Storage\IStorage;

class Model implements IStorage, ISoftDeletable
{
    use CreatedAtDate;
    use UpdatedAtDate;
    use DeletedAtDate;
    use StorableTrait;

    /** @var int */
    protected $blogId;

    /**
     * @param int $blogId
     * @return self
     */
    public function setBlogId($blogId)
    {
        $this->blogId = $blogId;
        return $this;
    }

    /**
     * @return int
     */
    public function getBlogId()
    {
        return $this->blogId;
    }

    /**
     * Proxy filename request through to storage, as this mapper doesn't store
     * a filename.
     *
     * @return string
     */
    public function getFilename()
    {
        return $this->storage->getFilename();
    }
}
