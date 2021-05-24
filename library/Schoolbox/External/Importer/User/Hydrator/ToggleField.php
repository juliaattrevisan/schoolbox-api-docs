<?php

/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\External\Importer\User\Hydrator;

use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\User as UserModel;
use Schoolbox\UserMapper;

/**
 * Sets a toggle field in the user based on the value of a key in the imported row.
 *
 * If the value is (case-insensitive):
 * * Y, 1, On, True: set user field to true
 * * N, 0, Off, False: set user field to false
 * * Default: do not set field
 *
 * @package Schoolbox\External\Importer\User\Hydrator
 */
class ToggleField extends Field
{

    /**
     * Constructor
     *
     * @param string          $fieldname
     * @param string          $setMethod
     */
    public function __construct($fieldname, $setMethod)
    {
        parent::__construct($fieldname, $setMethod, true);
    }


    /**
     * @inheritdoc
     */
    public function hydrate($row, UserModel $user)
    {
        if (isset($row[$this->fieldname])) {
            $value = $this->parseStringBoolean($row[$this->fieldname]);
            if (isset($value)) {
                return call_user_func([$user, $this->setMethod], $value);
            }
        }
        return $user;
    }

    /**
     * Parses a string from a CSV field into a boolean value.
     *
     * @param string $value
     * @return bool or null if it isn't understandable
     */
    protected function parseStringBoolean($value)
    {
        switch (strtolower(trim($value))) {
            case 'y':
            case 'on':
            case 'true':
            case 1:
                return true;
            case 'n':
            case 'off':
            case 'false':
            case 0:
                return false;
            default:
                return null;
        }
    }
}
