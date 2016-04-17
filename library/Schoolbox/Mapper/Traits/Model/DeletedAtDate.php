<?php
/**
 * Trait for model classes - adds a deleted date to the model.
 *
 * Classes that use this trait should implement Schoolbox\ISoftDeletable,
 * as this class supplies an isDeleted() method.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Mapper\Traits\Model;

use Alaress_Date_Format;

trait DeletedAtDate
{
    /**
     * Deleted date
     *
     * @var Alaress_Date_Format
     */
    protected $deletedAt;

    /**
     * @return Alaress_Date_Format
     */
    public function getDeletedAt()
    {
        return $this->deletedAt;
    }

    /**
     * @param  Alaress_Date_Format $deletedAt
     * @return $this
     */
    public function setDeletedAt($deletedAt)
    {
        $this->deletedAt = $deletedAt;
        return $this;
    }

    /**
     * Is this object deleted?
     *
     * @return bool
     */
    public function isDeleted()
    {
        return ($this->getDeletedAt() instanceof Alaress_Date_Format && !$this->getDeletedAt()->isNull());
    }
}
