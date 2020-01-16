<?php
/**
 * Provides the common connection functionality to the Edumate connection classes
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Alaress\Database\Connector\Base as Connector;
use Schoolbox\Database\Factory as DatabaseFactory;

class External_Edumate_Connection
{
    /** @var Connector $connector */
    private $connector;

    /**
     * Constructor
     */
    public function __construct()
    {
        /** @noinspection PhpDeprecationInspection */
        $this->connector = DatabaseFactory::getInstanceExternal();
    }

    /**
     * Internal DB2-specific code to turn CAPITALISEDFIELDS into NotCapitalisedFields
     *
     * @param array $record
     * @param array $fieldNames
     * @return array
     */
    protected function normaliseFields(array $record, array $fieldNames)
    {
        foreach ($fieldNames as $field) {
            $upper = strtoupper($field);
            if (array_key_exists($upper, $record)) {
                $record[$field] = $record[$upper];
                unset($record[$upper]);
            }
        }
        return $record;
    }

    // Getters & Setters
    /**
     * @return Connector
     */
    protected function getConnection()
    {
        return $this->connector;
    }


    /**
     * Quote input for safety
     *
     * @param $value string
     * @return string
     */
    public function quote($value) {
        return db2_escape_string($value);
    }
}
