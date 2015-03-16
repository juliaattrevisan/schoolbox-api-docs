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

trait DeletedDate
{
    /**
     * Deleted date
     *
     * @var Alaress_Date_Format
     */
    protected $deletedDate;

    /**
     * @return Alaress_Date_Format
     */
    public function getDeletedDate()
    {
        return $this->deletedDate;
    }

    /**
     * @param  Alaress_Date_Format $deletedDate
     *
     * @return $this
     */
    public function setDeletedDate($deletedDate)
    {
        $this->deletedDate = $deletedDate;
        return $this;
    }

    /**
     * Is this object deleted?
     *
     * @return bool
     */
    public function isDeleted()
    {
        return $this->getDeletedDate() instanceof Alaress_Date_Format;
    }
}
