<?php

/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\External\Importer\UserProfile\Hydrator;

use Schoolbox\External\UserProfile as UserProfileModel;

/**
 * Sets a field in the user based on the value of a key in the imported row,
 * if that key exists in the row.
 *
 * @package Schoolbox\External\Importer\UserProfile\Hydrator
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
     * The set method of UserProfileModel to call in order to set the field.
     *
     * @var string
     */
    protected $setMethod;

    /**
     * Constructor
     *
     * @param string $fieldname
     * @param string $setMethod
     */
    public function __construct($fieldname, $setMethod)
    {
        $this->fieldname = $fieldname;
        $this->setMethod = $setMethod;
    }

    /**
     * @inheritdoc
     */
    public function hydrate($row, UserProfileModel $user)
    {
        if (isset($row[$this->fieldname])) {
            return call_user_func([$user, $this->setMethod], $row[$this->fieldname]);
        }
        return $user;
    }
}
