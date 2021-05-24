<?php

/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\External\Importer\User\Hydrator;

use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\User as UserModel;

/**
 * Sets a field in the user based on the value of a key in the imported row,
 * if that key exists in the row.
 *
 * @package Schoolbox\External\Importer\User\Hydrator
 */
class Field implements HydratorInterface
{
    /**
     * The key in $row to set the value in the user to.
     *
     * @var string
     */
    protected $fieldname;

    /**
     * The set method of UserModel to call in order to set the field.
     *
     * @var string
     */
    protected $setMethod;

    /**
     * Whether to trim the string
     *
     * @var boolean
     */
    protected $trim = false;

    /**
     * Constructor
     *
     * @param string $fieldname
     * @param string $setMethod
     * @param boolean $trim
     */
    public function __construct($fieldname, $setMethod, $trim)
    {
        $this->fieldname = $fieldname;
        $this->setMethod = $setMethod;
        $this->trim = $trim;
    }

    /**
     * @inheritdoc
     */
    public function hydrate($row, UserModel $user)
    {
        if (isset($row[$this->fieldname])) {
            if ($this->trim) {
                $row[$this->fieldname] = trim($row[$this->fieldname]);
            }
            return call_user_func([$user, $this->setMethod], ($row[$this->fieldname]));
        }
        return $user;
    }
}
