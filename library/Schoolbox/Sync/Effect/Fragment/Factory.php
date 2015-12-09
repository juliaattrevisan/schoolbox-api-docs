<?php
/**
 * Factory (FragmentFactory)
 *
 * A Factory which produces instances of Fragment based on which
 * fields were selected, and the properties of that field in the
 * UserMapper definition
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Sync\Effect\Fragment;

use \Schoolbox\Sync\Effect\Type\Mapper as EffectTypeMapper;
use \Schoolbox\UserMapper;

class Factory {
    const TYPE_BOOLEAN = 1;
    const TYPE_LOOKUP = 2;
    const TYPE_STRING = 3;
    const TYPE_COLLECTION = 4;

    /**
     * Manufacture a list of Schoolbox User fields to chooze from
     * This combines $allFieldKeys' data with its type, as derived
     * from the UserMapper definition
     */
    private $allFields = [];

    /**
     * A list of all the Schoolbox UserMapper fields to
     * allow in the Effect select dropdowns
     *
     * @var array
     */
    private $allFieldKeys = [
        'externalId' => 'External ID',

        'username' => 'Username',

        'title' => 'Title' ,
        'firstname' => 'Firstname',
        'lastname' => 'Surname',
        'year' => 'Year Level',

        'altEmail' => 'Alt Email Address',

        'campusCollection' => 'Campus',

        'residentialHouse' => 'Residential House',
        'schoolHouse' => 'School House',
        'boardingStatus' => 'Boarding Status',

        'localEmail' => 'Email Address from Username',
        'useInternalMail' => 'Use External Mail Client',
        'enabled' => 'Schoolbox Account Enabled',
        'webmail' => 'Enable Webmail Tab',
        'eportfolioEnable' => 'E-Portfolio Enabled',

        'role' => 'Role'
    ];

    /**
     * The current Ruleset
     * Synonymous with Role Type
     *
     * @var string
     */
    private $roleType = '';

    /**
     * A Lookup instance
     *
     * @var Lookup
     */
    private $lookup = null;

    /**
     * Constructor
     */
    public function __construct($roleType) {
        $this->setLookup(new Lookup());

        $this->setRoleType($roleType);
        $this->setAllFields($this->generateAllFields());
    }

    /**
     * Figure out what each desired field's type is, via the UserMapper
     * Also compile human-readable versions of the field
     */
    private function generateAllFields() {
        $allFields = [];

        $mapper = new UserMapper();
        $allMaps = $mapper->getMap();

        /* Get the associative array of fields
         */
        foreach ($this->getAllFieldKeys() as $key => $label) {
            $currentMap = $allMaps->getOffset($key);

            /* Condense types
             */
            switch ($currentMap['type']) {

                /* Both Boolean types (lol)
                 */
                case UserMapper::TYPE_BOOL_ENUM :
                case UserMapper::TYPE_BOOLEAN :
                    $allFields[$key] = [
                        'type' => self::TYPE_BOOLEAN,
                        'label' => $label
                    ];

                    break;

                /* Model Join (Lookup, typically)
                 */
                case UserMapper::TYPE_MODEL :
                    $allFields[$key] = [
                        'type' => self::TYPE_LOOKUP,
                        'label' => $label
                    ];

                    break;

                case UserMapper::TYPE_COLLECTION :
                     $allFields[$key] = [
                         'type' => self::TYPE_COLLECTION,
                         'label' => $label
                     ];

                    break;

                /* All other types
                 */
                default:
                    $allFields[$key] = [
                        'type' => self::TYPE_STRING,
                        'label' => $label
                    ];

                    break;
            }
        }

        return $allFields;
    }

    /**
     * Determine which Effect Fragment to load for this field
     * (boolean, lookup, etc)
     *
     * @param $mappingType
     * @return Fragment
     */
    public function produce($mappingType) {
        if ($mappingType == EffectTypeMapper::EFFECT_TYPE_LITERAL) {
            return new Fragment('@upsEffect/literal.twig');
        }
        else if ($mappingType == EffectTypeMapper::EFFECT_TYPE_MAP) {
            return new Fragment('@upsEffect/map.twig');
        }
    }

    /**
     * Return empty Fragment, prompting the user to choose
     * a User field to modify and a Mapping Type
     */
    public function produceEmpty() {
        return new Fragment('@upsEffect/empty.twig');
    }

    /**
     * Produce a Subfragment
     *
     * @param $userField
     * @return Fragment
     */
    public function produceType($userField) {
        if ($this->isBoolean($userField)) {
            return new Fragment('@upsEffect/subfragments/boolean.twig');
        }
        else if ($this->isLookup($userField)) {
            return new Fragment('@upsEffect/subfragments/lookup.twig', $this->getLookup()->findData($userField, $this->getRoleType()));
        }
        else if ($this->isString($userField)) {
            return new Fragment('@upsEffect/subfragments/string.twig');
        }
        else if ($this->isCollection($userField)) {
            return new Fragment('@upsEffect/subfragments/collection.twig', $this->getLookup()->findData($userField));
        }
    }

    /**
     * Determine whether the current field is a Boolean
     *
     * @param $field
     * @return bool
     */
    public function isBoolean($field) {
        if ($this->allFields[$field]['type'] == self::TYPE_BOOLEAN) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the current field is a Collection
     *
     * @param $field
     * @return bool
     */
    public function isCollection($field) {
        if ($this->allFields[$field]['type'] == self::TYPE_COLLECTION) {
            return true;
        }

        return false;
    }

    /**
     * Gets the key/value representation of $allFields
     *
     * @return array
     */
    public function getAllFieldsKeyValue() {
        $allFields = $this->getAllFields();

        $stack = [];

        foreach ($allFields as $key => $data) {
            $stack[$key] = $data['label'];
        }

        return $stack;
    }

    /**
     * Determine whether the current field is a Lookup
     *
     * @param $field
     * @return bool
     */
    public function isLookup($field) {
        if ($this->allFields[$field]['type'] == self::TYPE_LOOKUP) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the current field is a String
     *
     * @param $field
     * @return bool
     */
    public function isString($field) {
        if ($this->allFields[$field]['type'] == self::TYPE_STRING) {
            return true;
        }

        return false;
    }

    /* Getters/Setters
     */

    /**
     * Set Lookup instance
     *
     * @param Lookup $lookup
     */
    private function setLookup($lookup)
    {
        $this->lookup = $lookup;
    }

    /**
     * Get Lookup instance
     *
     * @return Lookup
     */
    private function getLookup()
    {
        return $this->lookup;
    }

    /**
     * Set all field keys
     *
     * @param array $allFieldKeys
     */
    private function setAllFieldKeys($allFieldKeys)
    {
        $this->allFieldKeys = $allFieldKeys;
    }

    /**
     * Get all field keys
     *
     * @return array
     */
    private function getAllFieldKeys()
    {
        return $this->allFieldKeys;
    }

    /**
     * Get all fields
     *
     * @return array
     */
    public function getAllFields() {
        return $this->allFields;
    }

    /**
     * Set all fields
     *
     * @param $allFields
     */
    private function setAllFields($allFields) {
        $this->allFields = $allFields;
    }

    /**
     * @param string $roleType
     */
    private function setRoleType($roleType)
    {
        $this->roleType = $roleType;
    }

    /**
     * @return string
     */
    private function getRoleType()
    {
        return $this->roleType;
    }


}