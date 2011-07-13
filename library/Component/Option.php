<?php

/**
 * 
 * @author      Alex Tworowsky <alex.tworowsky@alaress.com.au>
 * @copyright   2011 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 * 
 */
 
class Component_Option extends Model {

    /**
     * @var string Contains custom table name
     */
    public static $_table = 'component_option';

    /**
     * @return Orm
     */
    public function defaultOption () {

        return $this->belongs_to('Component_OptionDefault');

    }
}