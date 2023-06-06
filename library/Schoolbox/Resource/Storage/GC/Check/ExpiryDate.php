<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Resource\Storage\GC\Check;

use Alaress\Database\Connector\IConnector;

class ExpiryDate extends DbCheck
{
    /** @var int */
    protected int $window;

    /**
     * @param IConnector $db
     * @param int $window
     */
    public function __construct(IConnector $db, int $window)
    {
        parent::__construct($db);
        $this->window = $window;
    }
    /**
     * Return the SQL condition that this check uses to regard a file as 'stale'.
     * Do not include the WHERE keyword.
     *
     * @return string
     */
    protected function getStaleCondition(): string
    {
        return "last_accessed < DATE_SUB(NOW(), INTERVAL $this->window MONTH)";
    }
}
