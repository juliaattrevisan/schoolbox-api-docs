<?php
/**
 * Synchronise_Tass_Meriden_Strategy_Student
 *
 * Update Schoolbox with Tass data if there is something different
 * contained in Tass database.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 * @shamelessly_pilfered_from <steve.chan@alaress.com.au>, <matt.day@alaress.com.au>
 */
class Synchronise_Tass_Meriden_Strategy_Student extends Synchronise_Base_Strategy_Student {

    /**
     * Get a list of students to sync
     *
     * @return void
     */
    public function generateList() {
        $this->list = $this->external_user_handler->getStudentUserAccountInfo();
    }

    /**
     * Setup verbose output and hydrator classes.
     */
    protected function setup() {
        $this->verbose_filter = new Synchronise_Tass_Meriden_Verbose();
        $this
            ->addHydrator(new Synchronise_Base_Strategy_Hydrator_Student())
            ->addHydrator(new Synchronise_Tass_Meriden_Hydrator_House())
            ->addHydrator(new Synchronise_Tass_Meriden_Hydrator_Campus())
            ->addHydrator(new Synchronise_Tass_Meriden_Hydrator_Student_Group())
            ->addHydrator(new Synchronise_Base_Strategy_Hydrator_Clear())
        ;
    }
}
