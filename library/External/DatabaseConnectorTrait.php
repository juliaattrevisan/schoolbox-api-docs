<?php
/**
 * Setups up an external MSSQL database connection. This is a temporary clutch until these old externals are
 * deprecated in favor of NDS which can get a connection using dependency injection.
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\External;

use Alaress\Database\Connector\Base as Connector;
use Alaress\Database\Factory as DatabaseFactory;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\IConfigLoader;

trait DatabaseMSSQLConnectorTrait
{
    /** @var Connector $connector */
    protected $connector;

    /** @var IConfigLoader $config */
    protected $config;

    /**
     * Setup external database connection
     */
    protected function setupDatabase()
    {
        $factory = new DatabaseFactory();
        $this->connector = $factory->createSqlsrv();
        $this->config = ConfigMapper::getInstance();

        $this->connector->connect(
            $this->config->get('external_db_host'),
            $this->config->get('external_db_db'),
            $this->config->get('external_db_user'),
            $this->config->get('external_db_passwd')
        );
    }

    /**
     * @return Connector
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
