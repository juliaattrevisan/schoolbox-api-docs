<?php

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
     * @var MSSQL
     */
    private $database;


    /**
     * Constructor
     */
    public function __construct()
    {
        $this->setDatabase(MSSQL::getInstance());
    }


    /**
     * Figure out an always-valid spouse prefix
     */
    public function getSpousePrefix(): string
    {
        $spousePrefix = trim(Config::get('external_tass_spouse_prefix'));

        return '' !== $spousePrefix ?: self::DEFAULT_SPOUSE_PREFIX;
    }


    /**
     * @param MSSQL $database
     */
    private function setDatabase($database)
    {
        $this->database = $database;
    }

    /**
     * @return MSSQL
     */
    private function getDatabase()
    {
        return $this->database;
    }


    /**
     * @return string
     */
    public function getAddNum()
    {
        $addnum = '';
        $addnum_array = array_unique(explode(',',trim(Config::get('external_tass_addnum'))));

        foreach ($addnum_array as $value) {
            $addnum = $addnum . ',' . $this->getDatabase()->smartQuote(trim($value));
        }
        if (strlen($addnum) > 0 ) {
            $addnum = substr($addnum,1);
        } else {
            $addnum = "'1','4','5'"; // default matching previous hardcodes
        }

        return $addnum;
    }

    /**
     * @return string
     */
    public function getAddNumMother()
    {
        $addnum = $this->getDatabase()->smartQuote(trim(Config::get('external_tass_addnum_mother')));
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
        $addnum = $this->getDatabase()->smartQuote(trim(Config::get('external_tass_addnum_father')));
        if ($addnum == "''" ) {
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
        $typecode_array = array_unique(explode(',',trim(Config::get('external_tass_commrules_typecode'))));

        foreach ($typecode_array as $value) {
            $typecode = $typecode . ',' . $this->getDatabase()->smartQuote(trim($value));
        }
        if (strlen($typecode) > 0 ) {
            $typecode = substr($typecode,1);
        }

        return $typecode;
    }
}
