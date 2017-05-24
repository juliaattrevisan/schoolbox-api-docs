<?php

class External_Tass_Helper
{
    /**
     * An instance of MSSQL
     * @var MSSQL
     */
    private $database = null;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->setDatabase(MSSQL::getInstance());
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
        $addnum = '';
        $addnum = $this->getDatabase()->smartQuote(trim(Config::get('external_tass_addnum_mother')));
        if (strlen($addnum) < 1 ) {
            $addnum = "'4'"; // TASS default
        }

        return $addnum;
    }

    /**
     * @return string
     */
    public function getAddNumFather()
    {
        $addnum = '';
        $addnum = $this->getDatabase()->smartQuote(trim(Config::get('external_tass_addnum_mother')));
        if (strlen($addnum) < 1 ) {
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
