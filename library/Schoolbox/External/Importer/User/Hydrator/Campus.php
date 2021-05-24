<?php

/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\External\Importer\User\Hydrator;

use Schoolbox\CampusMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\User as UserModel;

/**
 * Sets campuses for a user based on a lookup.
 *
 * @package Schoolbox\External\Importer\User\Hydrator
 */
class Campus implements HydratorInterface
{
    /**
     * The key in $row to set the value in the user to.
     *
     * @var string
     */
    protected $fieldname;

    /**
     * @var array
     */
    protected $lookupValues;

    /**
     * Constructor
     *
     * @param string $fieldname
     * @param array  $lookupValues
     */
    public function __construct($fieldname, $lookupValues)
    {
        $this->fieldname    = $fieldname;
        $this->lookupValues = $lookupValues;
    }

    /**
     * @inheritdoc
     */
    public function hydrate($row, UserModel $user)
    {
        // Does the field exist in $row?
        if (isset($row[$this->fieldname])) {
            // Set based on code or name? Maybe name.
            $campusList = CampusMapper::getInstance()->createCollection();
            foreach (explode(',', $row[$this->fieldname]) as $campusName) {
                $campusName = trim($campusName);
                if (strlen($campusName)) {
                    if (array_key_exists($campusName, $this->lookupValues)) {
                        $campusList->add($this->lookupValues[$campusName]);
                    }
                }
            }
            if (!empty($campusList)) {
                $user->setCampusCollection($campusList);
            }
        }
        return $user;
    }
}
