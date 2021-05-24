<?php

/**
 * Trait for model classes - adds a created date to the model.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Mapper\Traits\Model;

use Alaress_Date_Format;

trait CreatedAtDate
{
    /**
     * Created date
     *
     * @var Alaress_Date_Format
     */
    protected $createdAt;

    /**
     * @return Alaress_Date_Format
     */
    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    /**
     * @param  Alaress_Date_Format $createdAt
     *
     * @return $this
     */
    public function setCreatedAt(Alaress_Date_Format $createdAt = null)
    {
        $this->createdAt = $createdAt;
        return $this;
    }
}
