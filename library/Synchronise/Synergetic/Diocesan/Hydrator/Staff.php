<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Synchronise\Synergetic\Diocesan\Hydrator;


use External_UserInterface;
use Synchronise_Base_Hydrator_Staff;

class Staff extends Synchronise_Base_Hydrator_Staff
{

    const ROLE_STAFF = 15;

    const CAMPUS_JUNIOR  = 1;
    const CAMPUS_JUNHIGH = 2;
    const CAMPUS_SENIOR  = 3;


    /** @var External_UserInterface */
    protected $euHandler;


    /**
     * @param External_UserInterface $eu
     */
    function __construct(External_UserInterface $eu)
    {
        $this->euHandler = $eu;
    }


    /**
     * @param array $externalUser
     * @param array $newUser
     * @return array
     */
    public function hydrate($externalUser, $newUser)
    {
        $newUser = parent::hydrate($externalUser, $newUser);

        $newUser['group_id'] = self::ROLE_STAFF;

        /*
         * IF externalUser.StaffCategoryType NOT = TEACHER
         * AND user NOT EXISTS
         * THEN campusCollection = [
         *   Senior School (3),
         *   Junior High (2)
         *   Junior School (1)
         * ]
         * AND defaultCampus = Senior School (3)
         */
        // non teachers go in all campus
        if ($externalUser['StaffCategory'] != 'TEACH') {
            $newUser['campus'] = array(self::CAMPUS_SENIOR, self::CAMPUS_JUNHIGH,self::CAMPUS_JUNIOR);
            $newUser['defaultCampus'] = $newUser['campus'][0];

            return $newUser;
        }

        // loosey goosey
        $synCampi = $this->euHandler->getCampusFromTimetable($externalUser['StaffID']) ?: array();


        /*
         * TODO put some smarts in here: if default campus is set, that campus must also be auto-set in the campusCollection
         *
         * IF externalUser.StaffCategoryType = TEACHER
         * THEN defaultCampus MAPS externalUser.StaffCampus AS [
         *   "S" => [
         *     Senior School (3),
         *     Junior High (2)
         *   ],
         *   "J" => [
         *     Junior School (1)
         *   ],
         *   "F" => [
         *     Junior School (1)
         *   ]
         * ]
         */
        // append their 'default' campus
        if ($externalUser['StaffCampus']) {
            array_unshift($synCampi, array('campus' => $externalUser['StaffCampus']));
        }

        $u = new \Schoolbox\User();
        $u->getEportfolioEnable();

        /*
         * TODO somehow pull the campus codes through as defined by External_User::getCampusFromTimetable()
         *
         * IF externalUser.StaffCategoryType = TEACHER
         * THEN campusCollection MAPS COLLATE({External_User::getCampusFromTimetable(externalUser.StaffID)}) AS [
         *   "S" => [
         *     Senior School (3),
         *     Junior High (2)
         *   ],
         *   "J" => [
         *     Junior School (1)
         *   ],
         *   "F" => [
         *     Junior School (1)
         *   ]
         * ]
         */
        $map = array(
            'S' => array(self::CAMPUS_SENIOR, self::CAMPUS_JUNHIGH),
            'J' => array(self::CAMPUS_JUNIOR),
            'F' => array(self::CAMPUS_JUNIOR),
        );

        $boxCampi = array();

        // cast to schoolbox
        foreach ($synCampi as $cs)
        {
            if (array_key_exists($cs['campus'], $map)) {
                foreach ($map[$cs['campus']] as $c) {
                    $boxCampi[$c] = true;
                }
            }
        }

        // extract
        $boxCampi = array_keys($boxCampi);

        if (count($boxCampi)) {
            $newUser['campus'] = $boxCampi;
            $newUser['defaultCampus'] = $boxCampi[0];
        }

        return $newUser;

    }

} 