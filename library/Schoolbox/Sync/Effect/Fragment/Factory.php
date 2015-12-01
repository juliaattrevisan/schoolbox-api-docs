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
        'year' => 'Year',

        'email' => 'Email Address',
        'altEmail' => 'Alt Email Address',

        'residentialHouse' => 'Residential House',
        'schoolHouse' => 'School House',

        'boardingStatus' => 'Boarding Status',

        'localEmail' => 'Local Email?',
        'useInternalMail' => 'Use Internal Mail',
        'enabled' => 'Schoolbox Account Enabled?',
        'webmail' => 'Webmail',
        'eportfolioEnable' => 'Eportfolio Enabled?',
    ];

    /**
     * A Lookup instance
     *
     * @var Lookup
     */
    private $lookup = null;

    /**
     * Constructor
     */
    public function __construct() {
        $this->setLookup(new Lookup());

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
     * @param $userField
     * @return Fragment
     */
    public function produce($mappingType, $userField) {
        if ($mappingType == EffectTypeMapper::EFFECT_TYPE_LITERAL) {
            if ($this->isBoolean($userField)) {
                return new Fragment('@syncEffect/boolean.twig');
            }
            else if ($this->isLookup($userField)) {
                return new Fragment('@syncEffect/lookup.twig', $this->getLookup()->findData($userField));
            }
            else if ($this->isString($userField)) {
                return new Fragment('@syncEffect/string.twig');
            }
        }
        else if ($mappingType == EffectTypeMapper::EFFECT_TYPE_MAP) {
            return new Fragment('@syncEffect/map.twig');
        }

        /* Return empty Fragment, prompting the user to choose
         * a User field to modify and a Mapping Type
         */
        return new Fragment('@syncEffect/empty.twig');
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
}