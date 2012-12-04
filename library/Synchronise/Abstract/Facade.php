<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
abstract class Synchronise_Abstract_Facade {

    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function getDB() {
        return $this->db;
    }
}
