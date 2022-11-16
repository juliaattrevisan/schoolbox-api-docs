<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Mapper\Traits\Model;

use Alaress_Date_Format;

/**
 * Trait for model classes - adds a created date to the model.
 *
 * Most, but not all, created at dates are not nullable in the schema: this
 * trait needs to account for those which are nullable.
 */
trait CreatedAtDate
{
    /**
     * Created date
     *
     * @var Alaress_Date_Format|null
     */
    protected ?Alaress_Date_Format $createdAt = null;

    /**
     * @return Alaress_Date_Format|null
     */
    public function getCreatedAt(): ?Alaress_Date_Format
    {
        return $this->createdAt;
    }

    /**
     * @param  Alaress_Date_Format|null $createdAt
     * @return $this
     */
    public function setCreatedAt(?Alaress_Date_Format $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }
}
