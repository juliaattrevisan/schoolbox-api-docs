<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Mapper\Traits\Model;

use Alaress_Date_Format;

/**
 * Trait for model classes - adds a deleted date to the model.
 *
 * Classes that use this trait should implement Schoolbox\ISoftDeletable,
 * as this class supplies an isDeleted() method.
 */
trait DeletedAtDate
{
    /**
     * Deleted date
     *
     * @var Alaress_Date_Format|null
     */
    protected ?Alaress_Date_Format $deletedAt = null;

    /**
     * @return Alaress_Date_Format|null
     */
    public function getDeletedAt(): ?Alaress_Date_Format
    {
        return $this->deletedAt;
    }

    /**
     * @param  Alaress_Date_Format|null $deletedAt
     * @return $this
     */
    public function setDeletedAt(?Alaress_Date_Format $deletedAt = null): static
    {
        $this->deletedAt = $deletedAt;
        return $this;
    }

    /**
     * Is this object deleted?
     *
     * @return bool
     */
    public function isDeleted(): bool
    {
        return $this->getDeletedAt() instanceof Alaress_Date_Format;
    }
}
