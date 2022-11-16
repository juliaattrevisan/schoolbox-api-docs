<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Mapper\Traits\Model;

use Alaress_Date_Format;

/**
 * Trait for model classes - adds a modified date to the model.
 *
 * Most, but not all, updated at dates are not nullable in the schema: this
 * trait needs to account for those which are nullable.
 */
trait UpdatedAtDate
{
    /**
     * Modified date
     *
     * @var Alaress_Date_Format|null
     */
    protected ?Alaress_Date_Format $updatedAt = null;

    /**
     * @return Alaress_Date_Format|null
     */
    public function getUpdatedAt(): ?Alaress_Date_Format
    {
        return $this->updatedAt;
    }

    /**
     * @param  Alaress_Date_Format|null $updatedAt
     * @return $this
     */
    public function setUpdatedAt(?Alaress_Date_Format $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }
}
