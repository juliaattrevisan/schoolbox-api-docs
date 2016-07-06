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

trait ModifiedAtDate
{
    /**
     * Modified date
     *
     * @var Alaress_Date_Format
     */
    protected $modifiedAt;

    /**
     * @return Alaress_Date_Format
     */
    public function getModifiedAt()
    {
        return $this->modifiedAt;
    }

    /**
     * @param  Alaress_Date_Format $modifiedAt
     *
     * @return $this
     */
    public function setModifiedAt($modifiedAt)
    {
        $this->modifiedAt = $modifiedAt;
        return $this;
    }
}
