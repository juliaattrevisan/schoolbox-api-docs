<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\External\Connector\Pcschools\QueryHelper;

use Alaress\Database\Connector\IConnector;
use MSSQL;
use TypeError;


class Factory
{
    /** @var IConnector */
    private $connector;


    /**
     * @param IConnector|MSSQL $connector
     * @throws TypeError
     */
    public function __construct($connector)
    {
        // poor man's polymorphism
        if (!($connector instanceof IConnector || $connector instanceof MSSQL)) {
            throw new TypeError('Connector must be either an IConnector or MSSQL instance');
        }

        $this->connector = $connector;
    }


    /**
     * @return IQueryHelper
     */
    public function spawn()
    {
        // blegh...
        if ($this->connector instanceof MSSQL) {
            $stmt = $this->connector->query(IQueryHelper::FREEDOM_DETECT);

        } elseif ($this->connector instanceof IConnector) {
            $stmt = $this->connector->executeQuery(IQueryHelper::FREEDOM_DETECT);
        }

        /** @noinspection PhpUndefinedVariableInspection */
        return $stmt->fetchColumn()
            ? new Freedom()
            : new NonFreedom();
    }
}