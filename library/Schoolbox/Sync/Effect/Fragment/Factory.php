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

class Factory {
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
        'title' => self::TYPE_STRING,
        'useInternalMail' => self::TYPE_BOOLEAN,
        'username' => self::TYPE_STRING,
        'year' => self::TYPE_STRING,
        'enabled' =>  self::TYPE_BOOLEAN,
        'boardingStatus' => self::TYPE_STRING,
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
    }

    /**
     * Get the associative array of fields
     *
     * @return array
     */
    public function getAllFields() {
        return $this->allFields;
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
                return new Fragment('@syncEffect/boolean.twig', []);
            }
            else if ($this->isLookup($userField)) {
                return new Fragment('@syncEffect/lookup.twig', $this->getLookup()->findData($userField));
            }
            else if ($this->isString($userField)) {
                return new Fragment('@syncEffect/string.twig', []);
            }
        }

        return new Fragment('@syncEffect/map.twig', []);
    }

    /**
     * Determine whether the current field is a Boolean
     *
     * @param $field
     * @return bool
     */
    public function isBoolean($field) {
        if ($this->allFields[$field] == self::TYPE_BOOLEAN) {
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
        if ($this->allFields[$field] == self::TYPE_LOOKUP) {
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
        if ($this->allFields[$field] == self::TYPE_STRING) {
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
}