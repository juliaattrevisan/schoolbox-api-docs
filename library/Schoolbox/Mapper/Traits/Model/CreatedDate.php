<?php
/**
 * Trait for model classes - adds a created date to the model.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Mapper\Traits\Model;

use Alaress_Date_Format;

trait CreatedDate
{
    /**
     * Created date
     *
     * @var Alaress_Date_Format
     */
    protected $createdDate;

    /**
     * @return Alaress_Date_Format
     */
    public function getCreatedDate()
    {
        return $this->createdDate;
    }

    /**
     * @param  Alaress_Date_Format $createdDate
     *
     * @return $this
     */
    public function setCreatedDate($createdDate)
    {
        $this->createdDate = $createdDate;
        return $this;
    }
}
