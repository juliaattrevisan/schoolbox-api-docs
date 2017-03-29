<?php

/**
 * MSSQL Database Connector
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Alaress\Database\Connector\Sqlsrv;

use Alaress\Database\Broker\Sqlsrv\Broker;
use Alaress\Database\Connector\Base;

class Connector extends Base
{
    /** @var Broker */
    protected $broker;

    /**
     * @inheritdoc
     */
    public function newBroker($connector)
    {
        return new Broker($connector);
    }
}