<?php

/**
 * 
 * @author      Alex Tworowsky <alex.tworowsky@alaress.com.au>
 * @copyright   2011 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 * 
 */
 
abstract class Collection {
    protected $table;

    protected $identifier;

    public function getAll () {
        $resultSet = new ResultSet($query);
    }
}

class User {
    public $data

$data->firstname;
    $data->lastname;
    $data->other_metadata;


}






class UserCollection extends Collection {


}



class ResultSet {
    private $query;
    private $resultSet;


    public function __construct($query) {
        $this=>resultSet = db:query($query)
        $this->query = $query;

    }
}
