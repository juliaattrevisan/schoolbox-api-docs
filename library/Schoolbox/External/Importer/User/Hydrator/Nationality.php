<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\External\Importer\User\Hydrator;

use Schoolbox\Nationality\Mapper as NationalityMapper;
use Schoolbox\User as UserModel;
use SensitiveParameter;

/**
 * Sets the nationality collection in the user based on the value of a key in the imported row,
 *
 * @package Schoolbox\External\Importer\User\Hydrator
 */
class Nationality implements HydratorInterface
{
    /**
     * Constructor
     *
     * @param string $fieldname
     * @param array $lookupValues
     *
     */
    public function __construct(
        protected string $fieldname,
        protected array $lookupValues
    ) {
    }

    /**
     * @inheritdoc
     */
    public function hydrate(#[SensitiveParameter] array $row, UserModel $user): UserModel
    {
        // Does the field exist in $row?
        if (isset($row[$this->fieldname])) {
            // Set based on code or name? Maybe name.
            $nationalityList = NationalityMapper::getInstance()->createCollection();
            foreach (explode(',', $row[$this->fieldname]) as $nationalityName) {
                $nationalityName = trim($nationalityName);
                if (strlen($nationalityName)) {
                    if (array_key_exists($nationalityName, $this->lookupValues)) {
                        $nationalityList->add($this->lookupValues[$nationalityName]);
                    }
                }
            }
            if (count($nationalityList) > 0) {
                $user->setNationalityCollection($nationalityList);
            }
        }
        return $user;
    }
}
