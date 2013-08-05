<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
abstract class Facade_Generic extends Generic_Object {

    protected $components = array();

    public function __construct($responseCode) {
        parent::__construct($responseCode);
    }







    protected function addComponent(Facade_Component_Generic $component) {
        $this->components[] = $component;
    }

    protected function removeComponent($sequence) {
        unset($this->components[$sequence]);
    }
}
