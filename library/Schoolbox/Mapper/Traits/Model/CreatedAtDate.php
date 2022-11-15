<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Mapper\Traits\Model;

use Alaress_Date_Format;

/**
 * Trait for model classes - adds a created date to the model.
 */
trait CreatedAtDate
{
    /**
     * Created date
     *
     * @var Alaress_Date_Format
     */
    protected Alaress_Date_Format $createdAt;

    /**
     * @return Alaress_Date_Format
     */
    public function getCreatedAt(): Alaress_Date_Format
    {
        return $this->createdAt;
    }

    /**
     * @param  Alaress_Date_Format $createdAt
     * @return $this
     */
    public function setCreatedAt(Alaress_Date_Format $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }
}
