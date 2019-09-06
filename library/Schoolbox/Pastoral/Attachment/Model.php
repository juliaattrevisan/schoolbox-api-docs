<?php
/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Pastoral\Attachment;

use Schoolbox\BaseModel;
use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Exception\InsufficientHydrationException;
use Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Mapper\Traits\Model\UpdatedAtDate;
use Schoolbox\Pastoral\Model as Pastoral;
use Schoolbox\Resource\File\StorableTrait;
use Schoolbox\Resource\Storage\IStorage;

/**
 * Class Model
 *
 * @package Schoolbox\Pastoral\Attachment
 */
class Model extends BaseModel implements IStorage, ISoftDeletable
{
    use CreatedAtDate;
    use UpdatedAtDate;
    use DeletedAtDate;
    use StorableTrait;

    /** @var Pastoral $pastoral */
    private $pastoral;

    /** @var bool $private */
    private $private;


    /**
     * Returns whether this is a public attachment
     *
     * @return bool
     * @throws InsufficientHydrationException
     */
    public function isPublic(): bool
    {
        if ($this->private === null) {
            throw new InsufficientHydrationException("Expected bool, found null");
        }
        return !$this->private;
    }


    /**
     * Returns whether this is a private attachment
     *
     * @return bool
     * @throws InsufficientHydrationException
     */
    public function isPrivate(): bool
    {
        if ($this->private === null) {
            throw new InsufficientHydrationException("Expected bool, found null");
        }
        return !$this->private;
    }

    /**
     * @param Pastoral $pastoral
     *
     * @return self
     */
    public function setPastoral(Pastoral $pastoral)
    {
        $this->pastoral = $pastoral;

        return $this;
    }

    /**
     * @return Pastoral
     */
    public function getPastoral()
    {
        return $this->pastoral;
    }

    /**
     * @param bool $private
     *
     * @return self
     */
    public function setPrivate($private)
    {
        $this->private = $private;

        return $this;
    }

    /**
     * @return bool
     */
    public function getPrivate()
    {
        return $this->private;
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
