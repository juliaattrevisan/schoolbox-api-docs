<?php

/**
 * 
 * @author      Alex Tworowsky <alex.tworowsky@alaress.com.au>
 * @copyright   2011 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 * 
 */
 
class ActiveRecord {

    protected function create() {

    }

    protected function delete() {

    }

    protected function save() {

    }

    protected function update() {

    }
}

class User extends ActiveRecord {

    public function setPassword($password) {
        $query = "UPDATE blah SET PASSSWORD = blah";

        Db::query($query);
    }

    public function __cosntruct() {
        return $this;
    }

    public function find($userID) {
        Db:query("SELECT * FROM USER WHERE USERID=$USERID")
    }
}


new User()->find


frontcontroller.$php_errormsg
        Db = new MSSQL();
Db = new MySQL();
Db = mysqli

        DB::setDriver(new mysqldbclass())