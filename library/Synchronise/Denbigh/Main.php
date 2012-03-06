<?php
/**
 * Synchronise_External_Denbigh_Main
 *
 * Main class for controlling a sync between Denbigh and Schoolbox.
 *
 * Based on Synchronise_Synergetic_Main
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Synchronise_Denbigh_Main {
    private $strategy = null;


    public function __construct($strategy = null) {
        if ($strategy !== null) {
            $this->setStrategy(new $strategy);
        }
    }


    public function generateTitle() {
        $this->getStrategy()->generateTitle();
    }


    /**
     * Main function for performing sync
     */
    public function synchronise() {
        $this->getStrategy()->generateList();
        $this->getStrategy()->create();
    }


    /* Getters and Setters */
    public function setStrategy($strategy) {
        $this->strategy = $strategy;
    }

    public function getStrategy() {
        return $this->strategy;
    }

    public function setPerformUpdate($bool) {
        $this->getStrategy()->setPerformUpdate($bool);
    }

    public function getPerformUpdate() {
        return $this->getStrategy()->getPerformUpdate();
    }

    public function setVerbose($bool) {
        $this->getStrategy()->setVerbose($bool);
    }

    public function getVerbose() {
        return $this->getStrategy()->getVerbose();
    }

}
