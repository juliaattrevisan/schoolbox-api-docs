<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
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
