<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Mapper\Traits\Model;

use Alaress_Date_Format;

/**
 * Trait for model classes - adds a modified date to the model.
 */
trait UpdatedAtDate
{
    /**
     * Modified date
     *
     * @var Alaress_Date_Format
     */
    protected Alaress_Date_Format $updatedAt;

    /**
     * @return Alaress_Date_Format
     */
    public function getUpdatedAt(): Alaress_Date_Format
    {
        return $this->updatedAt;
    }

    /**
     * @param  Alaress_Date_Format $updatedAt
     * @return $this
     */
    public function setUpdatedAt(Alaress_Date_Format $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }
}
