<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Resource\Storage\GC\Check;


use Database;

class ExpiryDate extends DbCheck
{

    /** @var int */
    protected $window;
    /**
     * @param Database $db
     * @param int $window
     */
    public function __construct(Database $db, $window)
    {
        parent::__construct($db);
        $this->window = (int)$window;
    }
    /**
     * Return the SQL condition that this check uses to regard a file as 'stale'.
     * Do not include the WHERE keyword.
     *
     * @return string
     */
    protected function getStaleCondition()
    {
        return "last_accessed < DATE_SUB(NOW(), INTERVAL $this->window MONTH)";
    }

} 