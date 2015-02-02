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

        // we only reset campi for teachers
        if ($externalUser['StaffCategoryType'] != 'TEACHER') {
            return $newUser;
        }

        $synCampi = $this->euHandler->getCampusFromTimetable($externalUser['StaffID']);

        // append their 'default' campus
        $synCampi[] = $externalUser['StaffCampus'];

        $map = array(
            'S' => array(self::CAMPUS_SENIOR, self::CAMPUS_JUNHIGH),
            'J' => array(self::CAMPUS_JUNIOR),
            'F' => array(self::CAMPUS_JUNIOR),
        );

        $boxCampi = array();

        // cast to schoolbox
        foreach ($synCampi as $cs)
        {
            if (isset($map[$cs])) {
                foreach ($map[$cs] as $c) {
                    $boxCampi[$c] = true;
                }
            }
        }

        // extract
        $boxCampi = array_keys($boxCampi);

        $newUser['campus'] = $boxCampi;
        $newUser['defaultCampus'] = $map[$externalUser['StaffCampus']][0];

        return $newUser;

    }

} 