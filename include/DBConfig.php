<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class DBConfig {

    protected $host   = SQL_host;
    protected $user   = SQL_user;
    protected $passwd = SQL_passwd;
    protected $db     = SQL_db;
    protected $dsn;

    protected function __construct() {
        $this->dsn = "mysql:host={$this->host};dbname={$this->db}";
    }
}