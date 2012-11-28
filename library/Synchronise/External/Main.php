<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Synchronise_External_Main extends Synchronise_Synergetic_Main {

    protected $definition;

    /**
     * Choose your strategy wisely
     *
     * @param null $strategy
     */
    public function __construct($strategy = null) {
        if ($strategy !== null) {

            $stratBits = explode('_', $strategy);
            $school = $stratBits[2];
            $strat = $stratBits[4];

            $defn = "Synchronise_External_{$school}_Strategy_Definition";
            $definition = new $defn();
            $this->definition = $definition->getDefinition($strat);


            $this->setStrategy(new $strategy($this->definition['csv'], $this->definition['fields']));


        }
    }

    /**
     * Retrieve an element of the definition for the current strategy
     * @param $key
     * @return bool
     */
    public function getDefinition($key) {
        return isset($this->definition[$key]) ? $this->definition[$key] : false;
    }

}
