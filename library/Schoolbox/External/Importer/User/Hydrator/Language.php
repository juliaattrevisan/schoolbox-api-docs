<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\External\Importer\User\Hydrator;

use Schoolbox\Language\Mapper as LanguageMapper;
use Schoolbox\User as UserModel;
use SensitiveParameter;

/**
 * Sets the language collection in the user based on the value of a key in the imported row,
 *
 * @package Schoolbox\External\Importer\User\Hydrator
 */
class Language implements HydratorInterface
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
            $languageList = LanguageMapper::getInstance()->createCollection();
            foreach (explode(',', $row[$this->fieldname]) as $languageName) {
                $languageName = trim($languageName);
                if (strlen($languageName)) {
                    if (array_key_exists($languageName, $this->lookupValues)) {
                        $languageList->add($this->lookupValues[$languageName]);
                    }
                }
            }
            if (count($languageList) > 0) {
                $user->setLanguageCollection($languageList);
            }
        }
        return $user;
    }
}
