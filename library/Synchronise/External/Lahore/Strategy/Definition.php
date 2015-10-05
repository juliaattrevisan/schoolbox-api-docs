<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Synchronise_External_Lahore_Strategy_Definition extends Synchronise_External_Strategy_Definition {

    /**
     * Defines the fields found within each strategy. The file location for each is expected to be defined in
     * Cfg::get('external_sync_STRATEGY_csv')
     */
    public function __construct() {

        $this->setDefinition('class',
            array('code', 'name')
        );

        $this->setDefinition('enrolment',
            array('Code', 'SchoolID')
        );

        $this->setDefinition('parent',
            array('parentID', 'title', 'firstName', 'lastName', 'networkLogin', 'password', 'externalEmail')
        );

        $this->setDefinition('parentrelation',
            array('studentID', 'parentID')
        );

        $this->setDefinition('staff',
            array('staffID', 'title', 'firstName', 'lastName', 'networkLogin', 'localEmail')
        );

        $this->setDefinition('student',
            array('studentID', 'firstName', 'lastName', 'networkLogin', 'localEmail', 'dateOfBirth', 'homeroom',
                  'yearLevel', 'houseName')
        );

        $this->setDefinition('timetable',
            array('classCode', 'day', 'period', 'room')
        );
    }

}
