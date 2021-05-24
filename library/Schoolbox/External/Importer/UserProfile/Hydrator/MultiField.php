<?php

/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\External\Importer\UserProfile\Hydrator;

use Schoolbox\External\UserProfile as UserProfileModel;

/**
 * Sets a field in the user based on the value of multiple keys in the imported row,
 * if all keys exist in the row.
 *
 * @package Schoolbox\External\Importer\UserProfile\Hydrator
 */
class MultiField implements HydratorInterface
{
    /**
     * The keys in $row to set the value in the user to.
     *
     * @var array
     */
    protected $fieldnames;

    /**
     * The set method of UserProfileModel to call in order to set the field.
     *
     * @var string
     */
    protected $setMethod;

    /**
     * Constructor
     *
     * @param array  $fieldnames
     * @param string $setMethod
     */
    public function __construct($fieldnames, $setMethod)
    {
        $this->fieldnames = $fieldnames;
        $this->setMethod  = $setMethod;
    }

    /**
     * @inheritdoc
     */
    public function hydrate($row, UserProfileModel $user)
    {
        $fieldValue = [];
        foreach ($this->fieldnames as $fieldname) {
            if (isset($row[$fieldname])) {
                $fieldValue[] = $row[$fieldname];
            } else {
                return $user;
            }
        }
        call_user_func([$user, $this->setMethod], trim(implode(' ', $fieldValue)));
        return $user;
    }
}
