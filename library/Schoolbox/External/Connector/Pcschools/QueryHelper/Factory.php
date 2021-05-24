<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\External\Connector\Pcschools\QueryHelper;

use Alaress\Database\Connector\IConnector;
use TypeError;

class Factory
{
    /** @var IConnector */
    private $connector;


    /**
     * @param IConnector $connector
     * @throws TypeError
     */
    public function __construct(IConnector $connector)
    {
        $this->connector = $connector;
    }


    /**
     * @return IQueryHelper
     */
    public function spawn()
    {
        $stmt = $this->connector->executeQuery(IQueryHelper::FREEDOM_DETECT);
        return $stmt->fetchColumn()
            ? new Freedom()
            : new NonFreedom();
    }
}
