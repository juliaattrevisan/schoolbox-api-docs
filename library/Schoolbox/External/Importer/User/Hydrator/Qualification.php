<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\External\Importer\User\Hydrator;

use Schoolbox\IB\Qualification\Mapper as QualificationMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\User as UserModel;
use SensitiveParameter;

/**
 * Sets qualifications for a user based on a lookup.
 *
 * @package Schoolbox\External\Importer\User\Hydrator
 */
class Qualification implements HydratorInterface
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
    public function hydrate(#[SensitiveParameter] array $row, UserModel $user): UserModel
    {
        // Does the field exist in $row?
        if (isset($row[$this->fieldname])) {
            // Set based on code or name? Maybe name.
            $qualificationList = QualificationMapper::getInstance()->createCollection();
            foreach (explode(',', $row[$this->fieldname]) as $qualificationName) {
                $qualificationName = trim($qualificationName);
                if (strlen($qualificationName)) {
                    if (array_key_exists($qualificationName, $this->lookupValues)) {
                        $qualificationList->add($this->lookupValues[$qualificationName]);
                    }
                }
            }
            if (count($qualificationList) > 0) {
                $user->setQualificationCollection($qualificationList);
            }
        }
        return $user;
    }
}
