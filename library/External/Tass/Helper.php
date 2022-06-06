<?php

use Alaress\Database\Connector\IConnector;
use Alaress\Database\Predicate\Bindings;
use Alaress\Database\Predicate\Builder as PredicateBuilder;
use Schoolbox\Database\Factory;

class External_Tass_Helper
{

    /**
     * Default spouse prefix
     *
     * @var string
     */
    private const DEFAULT_SPOUSE_PREFIX = 'S';


    /**
     * Spouse Prefix, usually a letter; default is 'S'
     *
     * @var string
     */
    private $spousePrefix;


    /**
     * An instance of MSSQL
     * @var IConnector
     */
    private $database;


    /**
     * Constructor
     */
    public function __construct()
    {
        $this->database = Factory::getInstanceExternal();
    }


    /**
     * Figure out an always-valid spouse prefix
     */
    public function getSpousePrefix(): string
    {
        $spousePrefix = trim(Config::get('external_tass_spouse_prefix'));

        return '' !== $spousePrefix ? $spousePrefix : self::DEFAULT_SPOUSE_PREFIX;
    }


    /**
     * @param IConnector $database
     */
    private function setDatabase($database)
    {
        $this->database = $database;
    }

    /**
     * @return IConnector
     */
    private function getDatabase()
    {
        return $this->database;
    }


    /**
     * Address number block ids (add_num) to filter paraddress table with
     * @return string
     */
    public function getAddNum()
    {
        $addnum = '';
        $addnum_array = array_unique(explode(',', trim(Config::get('external_tass_addnum'))));

        foreach ($addnum_array as $value) {
            $trimValue = trim($value);
            if ($trimValue !== '') {
                $addnum = $addnum . ',' . $this->getDatabase()->legacyQuote($trimValue);
            }
        }
        if (strlen($addnum) > 0) {
            $addnum = substr($addnum, 1);
        } else {
            $addnum = "'1','4','5'"; // default matching previous hardcodes
        }

        return $addnum;
    }

    /**
     * Bindings for address number block ids (paraddress.add_num)
     * @param string $bind
     * @return Bindings
     */
    public function getAddNumBindings(string $bind = 'addNum'): Bindings
    {
        $addNums = array_unique(explode(',', trim(Config::get('external_tass_addnum'))));
        return PredicateBuilder::getBindings($addNums, $bind);
    }

    /**
     * @return string
     */
    public function getAddNumMother()
    {
        $addnum = $this->getDatabase()->legacyQuote(trim(Config::get('external_tass_addnum_mother')));
        if ($addnum == "''") {
            $addnum = "'4'"; // TASS default
        }
        return $addnum;
    }

    /**
     * @return string
     */
    public function getAddNumFather()
    {
        $addnum = $this->getDatabase()->legacyQuote(trim(Config::get('external_tass_addnum_father')));
        if ($addnum == "''") {
            $addnum = "'5'"; // TASS default
        }

        return $addnum;
    }

    /**
     * @return string
     */
    public function getTypeCode()
    {
        $typecode = '';
        $typecode_array = array_unique(explode(',', trim(Config::get('external_tass_commrules_typecode'))));

        foreach ($typecode_array as $value) {
            $trimValue = trim($value);
            if ($trimValue !== '') {
                $typecode = $typecode . ',' . $this->getDatabase()->legacyQuote($trimValue);
            }
        }
        if (strlen($typecode) > 0) {
            $typecode = substr($typecode, 1);
        }

        return $typecode;
    }

    /**
     * Bindings for typeCode
     * @param string $bind
     * @return Bindings
     */
    public function getTypeCodeBindings(string $bind = 'typeCode'): Bindings
    {
        $typeCodes = array_unique(explode(',', trim(Config::get('external_tass_commrules_typecode'))));
        return PredicateBuilder::getBindings($typeCodes, $bind);
    }
}
