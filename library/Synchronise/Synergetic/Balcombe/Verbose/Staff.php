<?php
/**
 * Synchronise_Synergetic_Balcombe_Verbose_Staff
 *
 * Contains methods to output verbose messages
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Synchronise_Synergetic_Balcombe_Verbose_Staff extends Synchronise_Synergetic_Verbose_Abstract {
    protected $verbose = false;

    /**
     * Echo message about planning creation
     */
    public function planCreation() {
        if ($this->getVerbose() === true) {
            $this->printline('+ Plan create', $this->getNewUser());
        }
    }

    /**
     * Echo message about performing creation
     */
    public function performCreation() {
        if ($this->getVerbose() === true) {
            $this->printline('+ Perform create', $this->getNewUser());
        }
    }

    /**
     * Echo message about planning update
     *
     * @return void
     */
    public function planUpdate() {
        if ($this->getVerbose() === true) {
            $this->printline('^ Plan update', $this->getNewUser());
        }
    }

    /**
     * Echo message about performing update
     *
     * @return void
     */
    public function performUpdate() {
        if ($this->getVerbose() === true) {
            $this->printline('^ Perform update', $this->getNewUser());
        }
    }

    /**
     * Echo message about planning update
     *
     * @return void
     */
    public function skip() {
        if ($this->getVerbose() === true) {
            $this->printline('o Skip', $this->getNewUser());
        }
    }

    /**
     * Push the data to STDOUT
     * @param $intent
     * @param $action
     * @param $data
     */
    private function printline($action, $data) {

        printf(" %-16s of %-10s from %8s => %-52s %-52s (%-8s) %3s\n",
            $action,
            $data['username'],
            $data['synergy_id'],
            $data['title'] . '. ' . $data['firstname'] . ' ' . $data['lastname'],
            '<'.$data['alt_email'].'>',
            implode(' ',$data['campus']),
            $data['group_id']
        );

    }
}
