<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\External\Importer\User\Hydrator;

use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\User as UserModel;
use Schoolbox\UserMapper;

/**
 * Sets a field in the user based on the value of a key in the imported row,
 * if that key exists in the row and it isn't empty.
 *
 * The same as Field but doesn't set the value if it's empty.
 *
 * @package Schoolbox\External\Importer\User\Hydrator
 */
class FieldIgnoreEmpty extends Field
{
    /**
     * @inheritdoc
     */
    public function hydrate($row, UserModel $user)
    {
        if (isset($row[$this->fieldname]) && strlen($row[$this->fieldname]) > 0) {
            if ($this->trim) {
                $row[$this->fieldname] = trim($row[$this->fieldname]);
            }
            return call_user_func([$user, $this->setMethod], $row[$this->fieldname]);
        }
        return $user;
    }
}
