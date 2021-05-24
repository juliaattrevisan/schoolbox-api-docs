<?php

/**
 * Trait for model classes - adds a modified date to the model.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Mapper\Traits\Model;

use Alaress_Date_Format;

trait UpdatedAtDate
{
    /**
     * Modified date
     *
     * @var Alaress_Date_Format
     */
    protected $updatedAt;

    /**
     * @return Alaress_Date_Format
     */
    public function getUpdatedAt()
    {
        return $this->updatedAt;
    }

    /**
     * @param  Alaress_Date_Format $updatedAt
     *
     * @return $this
     */
    public function setUpdatedAt(Alaress_Date_Format $updatedAt = null)
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }
}
