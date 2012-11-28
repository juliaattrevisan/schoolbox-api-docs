<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
abstract class Synchronise_External_Strategy_Definition {

    private $definition;

    protected function setDefinition($strategy, $fields) {
        $strategy = strtolower($strategy);
        $this->definition[$strategy] = array(
            'csv' => Cfg::get("external_sync_{$strategy}_csv"),
            'fields' => $fields
        );
    }

    public function getDefinition($strategy) {
        $strategy = strtolower($strategy);
        if(isset($this->definition[$strategy])) {
            return $this->definition[$strategy];
        }
        return false;
    }

}
