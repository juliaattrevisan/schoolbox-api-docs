<?php
/**
 * Trait for model classes - adds a modified date to the model.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Mapper\Traits\Model;

use Alaress_Date_Format;

trait ModifiedDate
{
    /**
     * Modified date
     *
     * @var Alaress_Date_Format
     */
    protected $modifiedDate;

    /**
     * @return Alaress_Date_Format
     */
    public function getModifiedDate()
    {
        return $this->modifiedDate;
    }

    /**
     * @param  Alaress_Date_Format $modifiedDate
     *
     * @return $this
     */
    public function setModifiedDate($modifiedDate)
    {
        $this->modifiedDate = $modifiedDate;
        return $this;
    }
}
