<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

use Synchronise_Synergetic_Meggs_Hydrator_Student_Campus as Campus;

class Synchronise_Synergetic_Meggs_Hydrator_Staff_Campus implements Synchronise_Base_IHydrator {

    /** @var External_User */
    protected $euHandler;


    /**
     * @param External_UserInterface $euHandler
     */
    function __construct(External_UserInterface $euHandler)
    {
        $this->euHandler = $euHandler;
    }


    /**
     * Campus Mapping
     *
     * Converts data from the format from external database to the format used
     * in Schoolbox.
     *
     * This method should modify and return $newUser.
     *
     * @param  array $externalUser A result from the external database.
     * @param  array $newUser      The current result that will be inserted into
     *                             Schoolbox.
     *                             This result could have come from a hydrate()
     *                             call on another class that implements this
     *                             interface.
     * @return array The result to insert into Schoolbox,
     */
    public function hydrate($externalUser, $newUser)
    {
        $schoolboxCampuses = array();


        $synergyCampuses = $this->euHandler->getCampusFromTimetable($externalUser['StaffID']) ?: array();

        // for each campus returned, cast to the appropriate schoolbox campus list
        if(is_array($synergyCampuses)) {
            foreach ($synergyCampuses as $c) {
                switch ($c['campus']) {
                    case 'J':
                        $newUser['defaultCampus'] =  Campus::CAMPUS_JNR;
                        break;
                    case 'ELC':
                        $newUser['defaultCampus'] =  Campus::CAMPUS_ELC;
                        break;
                    case 's':
                    case 'S':
                        $newUser['defaultCampus'] = Campus::CAMPUS_YR12;
                        break;
                }
            }
        }



        // All staff in all campus
        $schoolboxCampuses[Campus::CAMPUS_ELC] = Campus::CAMPUS_ELC;
        $schoolboxCampuses[Campus::CAMPUS_JNR] = Campus::CAMPUS_JNR;
        $schoolboxCampuses[Campus::CAMPUS_YR7]  = Campus::CAMPUS_YR7;
        $schoolboxCampuses[Campus::CAMPUS_YR8]  = Campus::CAMPUS_YR8;
        $schoolboxCampuses[Campus::CAMPUS_YR9]  = Campus::CAMPUS_YR9;
        $schoolboxCampuses[Campus::CAMPUS_YR10] = Campus::CAMPUS_YR10;
        $schoolboxCampuses[Campus::CAMPUS_YR11] = Campus::CAMPUS_YR11;
        $schoolboxCampuses[Campus::CAMPUS_YR12] = Campus::CAMPUS_YR12;

        $newUser['campus'] = array_values($schoolboxCampuses);

        return $newUser;
    }

}