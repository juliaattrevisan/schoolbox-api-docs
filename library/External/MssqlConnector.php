<?php
/**
 * Setups up an external MSSQL database connection. This is a temporary clutch until these old externals are
 * deprecated in favor of NDS which can get a connection using dependency injection.
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

use Alaress\Database\Connector\IConnector;
use Schoolbox\Database\Factory as DatabaseFactory;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\IConfigLoader;

trait External_MssqlConnector
{
    /** @var IConnector $connector */
    protected $connector;

    /** @var IConfigLoader $config */
    protected $config;

    /**
     * Setup external database connection
     */
    protected function setupDatabase()
    {
        $this->connector = DatabaseFactory::getInstanceExternal();
        $this->config = ConfigMapper::getInstance();
    }

    /**
     * @return IConnector
     */
    protected function getConnector() {
        return $this->connector;
    }

    /**
     * @deprecated
     * @param string $value
     * @return string
     */
    public function quote($value)
    {
        return str_replace("'", "''", $value);
    }

    /**
     * @deprecated
     * @param string $value
     * @return string
     */
    public function smartQuote($value)
    {
        return "'" . $this->quote($value) . "'";
    }
}
