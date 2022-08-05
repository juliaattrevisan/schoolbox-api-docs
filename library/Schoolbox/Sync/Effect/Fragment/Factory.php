<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Sync\Effect\Fragment;

use Schoolbox\Sync\Effect\Type\Mapper as EffectTypeMapper;
use Schoolbox\UserMapper;
use Schoolbox\Config\Loader as ConfigLoader;

/**
 * Class Factory
 *
 * A Factory which produces instances of Fragment based on which
 * fields were selected, and the properties of that field in the
 * UserMapper definition
 *
 * @package Schoolbox\Sync\Effect\Fragment
 */
class Factory
{

    /**
     * Various types
     */
    private const TYPE_BOOLEAN = 1;
    private const TYPE_LOOKUP = 2;
    private const TYPE_STRING = 3;
    private const TYPE_COLLECTION = 4;


    /**
     * Manufacture a list of Schoolbox User fields to chooze from
     * This combines $allFieldKeys' data with its type, as derived
     * from the UserMapper definition
     */
    private $allFields;


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
        'preferredName' => 'Preferred Name',
        'lastname' => 'Surname',
        'year' => 'Year Level',
        'positionTitle' => 'Position Title',

        'role' => 'Role',
        'userAccessRead' => 'Groups (Read) (Disabled)',
        'userAccessWrite' => 'Groups (Write) (Disabled)',
        'userAccessRemove' => 'Groups (Remove) (Disabled)',

        'altEmail' => 'Alt Email Address',

        'campusCollection' => 'Campus Involvement',
        'campusCollectionRemove' => 'Campus Involvement (Remove)',

        'residentialHouse' => 'Residential House',
        'schoolHouse' => 'School House',
        'boardingStatus' => 'Boarding Status',

        'localEmail' => 'Email Address from Username',
        'useInternalMail' => 'Use External Mail Client',
        'enabled' => 'Schoolbox Account Enabled',
        'webmail' => 'Enable Webmail Tab',
        'eportfolioEnable' => 'E-Portfolio Enabled',
        'hideContacts' => 'Hide Contact Details',
        'hideTimetable' => 'Hide Timetable',
        'displayGradeGraph' => 'Display Comparative Graph on Grades',
    ];


    /**
     * Special ajax urls for selectize population
     * Uses same controller with different params
     *
     * @var array
     */
    private $allAjaxUrls = [
        'userAccessRead' => 'groups',
        'userAccessWrite' => 'groups',
        'userAccessRemove' => 'groups',
        'campusCollection' => 'campus',
        'campusCollectionRemove' => 'campus',
    ];


    /**
     * The current Ruleset
     * Synonymous with Role Type
     *
     * @var string
     */
    private $roleType;


    /**
     * A Lookup instance
     *
     * @var Lookup
     */
    private $lookup;


    /**
     * Factory constructor.
     *
     * @param string $roleType
     */
    public function __construct(string $roleType)
    {

        /* Determine if Groups perms are enabled before making them accessible
         */
        // TODO SA-8354: inject this
        $config = new ConfigLoader();

        /* Rename these if enabled
         */
        if ($config->get('ups_groups_enabled')) {
            $this->allFieldKeys['userAccessRead'] = 'Groups (Read)';
            $this->allFieldKeys['userAccessWrite'] = 'Groups (Write)';
            $this->allFieldKeys['userAccessRemove'] = 'Groups (Remove)';
        }

        $this->lookup = new Lookup();
        $this->roleType = $roleType;
        $this->allFields = $this->generateAllFields();
    }


    /**
     * Figure out what each desired field's type is, via the UserMapper
     * Also compile human-readable versions of the field
     *
     * @return array
     */
    private function generateAllFields(): array
    {
        $allFields = [];

        $mapper = new UserMapper();
        $allMaps = $mapper->getMap();
        $allJoins = $mapper->getJoin();
        $allFieldKeys = $this->allFieldKeys;

        /* Get the associative array of fields
         */
        foreach ($allFieldKeys as $key => $label) {
            $currentMap = $allMaps->getOffset($key);

            /* Condense types
             */
            switch ($currentMap['type'] ?? '') {
                /* Both Boolean types (lol)
                 */
                case UserMapper::TYPE_BOOL_ENUM:
                case UserMapper::TYPE_BOOLEAN:
                    $allFields[$key] = [
                        'type' => self::TYPE_BOOLEAN,
                        'label' => $label
                    ];

                    break;

                /* Model Join (Lookup, typically)
                 */
                case UserMapper::TYPE_MODEL:
                    $allFields[$key] = [
                        'type' => self::TYPE_LOOKUP,
                        'label' => $label
                    ];

                    break;

                case UserMapper::TYPE_COLLECTION:
                     $allFields[$key] = [
                         'type' => self::TYPE_COLLECTION,
                         'label' => $label
                     ];

                    break;

                /* All other types
                 */
                default:
                    /* At this stage, joins (such as UserAccess) would have been given
                     * self::TYPE_STRING. Check joins stack before assuming string
                     */
                    if (array_key_exists($key, $allJoins)) {
                        $allFields[$key] = [
                            'type' => self::TYPE_COLLECTION,
                            'label' => $label
                        ];
                    } else {
                        $allFields[$key] = [
                            'type'  => self::TYPE_STRING,
                            'label' => $label
                        ];
                    }

                    break;
            }
        }

        /* Special cases
         * These need special treatment/representation
         *
         * userAccessRead and Write aren't found in UserMapper explicitly,
         * we've made that up; we need to assign a key/value pair accordingly
         */
        $allSpecialCases = ['userAccessRead', 'userAccessWrite', 'userAccessRemove', 'campusCollectionRemove'];

        foreach ($allSpecialCases as $currentSpecialCase) {
            $allFields[$currentSpecialCase] = [
                'type'  => self::TYPE_COLLECTION,
                'label' => $allFieldKeys[$currentSpecialCase]
            ];
        }

        return $allFields;
    }


    /**
     * Determine which Effect Fragment to load for this field
     * (boolean, lookup, etc)
     *
     * @param int $mappingType
     * @return Fragment|null
     */
    public function produce(int $mappingType): ?Fragment
    {
        if ($mappingType === EffectTypeMapper::EFFECT_TYPE_LITERAL) {
            return new Fragment('@upsEffect/literal.twig');
        }

        if ($mappingType === EffectTypeMapper::EFFECT_TYPE_MAP) {
            return new Fragment('@upsEffect/map.twig');
        }

        /* You shouldn't be able to get here, but ok
         */
        return null;
    }


    /**
     * Return empty Fragment, prompting the user to choose
     * a User field to modify and a Mapping Type
     *
     * @return Fragment
     */
    public function produceEmpty(): Fragment
    {
        return new Fragment('@upsEffect/empty.twig');
    }


    /**
     * Produce a Subfragment
     *
     * @param string $userField
     * @return Fragment|null
     */
    public function produceType(string $userField): ?Fragment
    {
        if ($this->isBoolean($userField)) {
            return new Fragment('@upsEffect/subfragments/boolean.twig');
        }

        if ($this->isLookup($userField)) {
            $fragment = new Fragment('@upsEffect/subfragments/lookup.twig', $this->lookup->findData($userField, $this->roleType));

            /* Special case for 'year' - prompt user to choose from a lookup
             */
            if ('year' === $userField) {
                $fragment->setPrompt(true);
            }

            return $fragment;
        }

        if ($this->isString($userField)) {
            return new Fragment('@upsEffect/subfragments/string.twig');
        }

        if ($this->isCollection($userField)) {
            $fragment = new Fragment('@upsEffect/subfragments/collection.twig', $this->lookup->findData($userField));
            $fragment->setAjaxUrl($this->allAjaxUrls[$userField]);

            return $fragment;
        }

        /* You shouldn't be able to get here, but ok
         */
        return null;
    }


    /**
     * Determine whether the current field is a Boolean
     *
     * @param string $field
     * @return bool
     */
    public function isBoolean(string $field): bool
    {
        return ($this->allFields[$field]['type'] ?? null) === self::TYPE_BOOLEAN;
    }


    /**
     * Determine whether the current field is a Collection
     *
     * @param string $field
     * @return bool
     */
    public function isCollection(string $field): bool
    {
        return ($this->allFields[$field]['type'] ?? null) === self::TYPE_COLLECTION;
    }


    /**
     * Determine whether the current field is a Lookup
     *
     * @param string $field
     * @return bool
     */
    public function isLookup(string $field): bool
    {
        return ($this->allFields[$field]['type'] ?? null) === self::TYPE_LOOKUP;
    }


    /**
     * Determine whether the current field is a String
     *
     * @param string $field
     * @return bool
     */
    public function isString(string $field): bool
    {
        return ($this->allFields[$field]['type'] ?? null) === self::TYPE_STRING;
    }


    /**
     * Gets the key/value representation of $allFields
     *
     * @return array
     */
    public function getAllFieldsKeyValue(): array
    {
        $allFields = $this->allFields;

        $stack = [];

        foreach ($allFields as $key => $data) {
            $stack[$key] = $data['label'];
        }

        return $stack;
    }
}
