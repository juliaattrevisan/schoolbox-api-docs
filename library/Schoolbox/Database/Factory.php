<?php

namespace Schoolbox\Database;

use Alaress\Database\Connector\IConnector;
use Alaress\Database\Exception as DatabaseException;
use Alaress\Database\Factory as DatabaseFactory;
use Schoolbox\IConfigLoader;

class Factory
{
    /**
     * Retrieve an appropriate external database connection based on the 'external_type' set in Schoolbox configuration
     *
     * @param IConfigLoader $config Schoolbox configuration
     * @return IConnector|IConnector[] Return the external connector/connectors appropriate for the SIS
     */
    public function getExternalDatabase($config)
    {
        $factory = new DatabaseFactory();

        switch ($config['external_type']) {

            // Microsoft SQL Server

            case 'tass':
            case 'synergetic':
            case 'schoolpro':
            case 'pcschools':
            case 'maze':
            case 'isams':
            case 'doublefirst':
            case 'humanedge':

                $connectorExternal = $factory->createSqlsrv();
                break;

            // MySQL

            case 'schoolbox':
            case 'denbigh':

                $connectorExternal = $factory->createMysql();
                break;

            // IBM DB2

            case 'edumate':

                $connectorExternal = $factory->createDb2();
                break;
        };

        if (!isset($connectorExternal)) {
            throw new DatabaseException("Unknown external database type.");
        }

        if (!$connectorExternal->connect(
            $config['external_db_host'],
            $config['external_db_db'],
            $config['external_db_user'],
            $config['external_db_passwd']
        )) {
            throw new DatabaseException("Unable to connect to {$config['external_type']} database.");
        }

        // HumanEdge is a special little potato with two databases

        if ($config['external_type'] == 'humanedge') {

            $connectorAplus = $factory->createSqlsrv();

            if (!$connectorAplus->connect(
                $config['external_humanedge_aplus_db_host'],
                $config['external_humanedge_aplus_db_db'],
                $config['external_humanedge_aplus_db_user'],
                $config['external_humanedge_aplus_db_passwd']
            )) {
                throw new DatabaseException("Unable to connect to HumanEdge A+ database.");
            }

            // Return the connectors in an array

            $connectors = [];
            $connectors['external'] = $connectorExternal;
            $connectors['aplus'] = $connectorAplus;
            return $connectors;
        }

        // Everything else just gets one connector

        return $connectorExternal;
    }
}
