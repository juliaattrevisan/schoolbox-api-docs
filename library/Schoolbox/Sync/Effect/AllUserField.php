<?php
/**
 * AllUserField
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Sync\Effect;


class AllUserField {
    const TYPE_BOOLEAN = 1;
    const TYPE_LOOKUP = 2;
    const TYPE_STRING = 3;

    /* Manufacture a list of Schoolbox User fields to chooze from
     * TODO: Connect up to UserMapper to determine type
     * TODO: This should contain a human-friendly version
     */
    private $allFields = [
        'email' => self::TYPE_STRING,
        'altEmail' => self::TYPE_STRING,
        'webmail' => self::TYPE_BOOLEAN,
        'eportfolioEnable' => self::TYPE_BOOLEAN,
        'externalId' => self::TYPE_STRING,
        'firstname' => self::TYPE_STRING,
        'lastname' => self::TYPE_STRING,
        'localEmail' => self::TYPE_BOOLEAN,
        'residentialHouse' => self::TYPE_LOOKUP,
        'schoolHouse' => self::TYPE_LOOKUP,
        'title' => self::TYPE_LOOKUP,
        'useInternalMail' => self::TYPE_BOOLEAN,
        'username' => self::TYPE_STRING,
        'year' => self::TYPE_STRING,
        'enabled' =>  self::TYPE_BOOLEAN,
        'boardingStatus' => self::TYPE_STRING,
    ];

    public function __construct() {
    }

    public function getAllFields() {
        return $this->allFields;
    }

    public function getType($field) {
        return $this->allFields[$field];
    }

    /**
     * Determine which Effect Fragment to load for this field
     * (boolean, lookup, etc)
     *
     * @param $mappingType
     * @param $userField
     * @return string
     */
    public function getEffectFragmentTemplate($mappingType, $userField) {
        if ($mappingType == 'equals') {
            if ($this->isBoolean($userField)) {
                return '@syncEffect/boolean.twig';
            }
            else if ($this->isLookup($userField)) {
                return '@syncEffect/lookup.twig';
            }
            else if ($this->isString($userField)) {
                return '@syncEffect/varchar.twig';
            }
        }

        return '@syncEffect/map.twig';
    }


    public function isBoolean($field) {
        if ($this->allFields[$field] == self::TYPE_BOOLEAN) {
            return true;
        }

        return false;
    }

    public function isLookup($field) {
        if ($this->allFields[$field] == self::TYPE_LOOKUP) {
            return true;
        }

        return false;
    }

    public function isString($field) {
        if ($this->allFields[$field] == self::TYPE_STRING) {
            return true;
        }

        return false;
    }
} 