<?php

/**
 * 
 * @author      Alex Tworowsky <alex.tworowsky@alaress.com.au>
 * @copyright   2011 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 * 
 */
 
abstract class Model {
    /**
         * Defines the state of the model
         *
         * @var Bool
         */
    protected $state;

    /**
     * State constants
     */
    const STATE_CLEAN = 0;

    const STATE_DIRTY = 1;

    /**
     * Constructor
     */
    public function __construct () {
    }

    /**
         * Sets the current state of the model.
         *
         * @param Integer $state
         * @return Bool
         */
    public function setState ($state) {
        if (empty($state) || !is_numeric($state) ) {
            return false;
        }

        $this->state = $state;

        return true;
    }

    /**
         * Retrieves the current state of the model.
         *
         * @return Bool|Mixed
         */
    public function getState () {
        if ( empty($this->state) || !is_numeric($this->state) ) {
            return false;
        }

        return $this->state;
    }
    
}