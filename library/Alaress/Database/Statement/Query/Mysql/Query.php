<?php

/**
 * MySQL Query
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Alaress\Database\Statement\Query\Mysql;

use Alaress\Database\Broker\Mysql\Broker;
use Alaress\Database\Statement\Query\IQuery;
use Alaress\Database\Statement\Query\Base;

class Query extends Base implements IQuery
{
    /** @var Broker $broker */
    protected $broker;

    /**
     * Database statement constructor
     *
     * @param Broker $broker Database broker that this query belongs to
     * @param string $sql SQL query string to be executed
     */
    function __construct($broker, $sql) {
        $this->broker = $broker;
        $this->sql = $sql;
    }
}