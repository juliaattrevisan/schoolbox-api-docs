<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
use Schoolbox\Resource\CategoryMapper;
use Schoolbox\User;
use Schoolbox\UserMapper;

class Facade_Component_CurrentPeriod extends Facade_Component_Generic {

    public function render(User $user) {

        if($_SESSION['timetable'] == 0) {
            // return an array without title set, which will prevent the article rendering
            // *content needs to remain set
            return array('content'  => array());
        }

        try {

            /* REFACTOR */
            $timetableHandler = new External_Timetable();

            $eUser = new External_User();
            $eUser->getCurrentSemester();

            $campus = $user->getCampusCollection()->current()->getId();

            /*
            $dayNum = 4;
            $now = mktime(19,0,0);
            */
            $dayNum = $timetableHandler->getDayNumber($campus);
            $now = time();

            $def = $timetableHandler->getTimetableDefinition($campus, $dayNum);

            if(!count($def) || !is_array($def)) {
                // return an array without title set, which will prevent the article rendering
                // *content needs to remain set
                return array('content'  => array());
            }

            $dayCount = $def['dayCount'];

            // if we're still inside today

            $b = count($def['end']) - 1;
            $c = $def['end'][$b];
            if(strtotime($c) > $now) {

                $todayDef = $timetableHandler->getDayTimetable(
                    $user->getExternalId(),
                    $user->getId(),
                    $campus,
                    $dayNum
                );

            }
            // if we're off the end of today, show tomorrow
            else {

                $dayNum = ($dayNum + 1) % $dayCount;
                $dayNum = $dayNum == 0 ? $dayCount : $dayNum;  //dont let daynumber be 0


                $todayDef = $timetableHandler->getDayTimetable(
                    $user->getExternalId(),
                    $user->getId(),
                    $campus,
                    $dayNum
                );
            }




            $ttable = array();

            $currentFlag = false;
            for($i = 0; $i < count($def['end']); $i++) {
                $ttable[$i] = $todayDef[$i];
                if(!$currentFlag && strtotime($def['end'][$i]) > $now) {
                    $ttable[$i]['current'] = 1;
                    $currentFlag = true;
                }

            }

            // manipulate the datums!
	        /**
	         * @var UserMapper $uMap
	         * @var CategoryMapper $cMap
	         */
	        $uMap = UserMapper::getInstance();
			$cMap = CategoryMapper::getInstance();

            foreach($ttable as &$period) {
                if(isset($period['staffID'])) {

                    try {
	                    /** @var User $teacher */
	                    $teacher = $uMap->findByColumn('synergy_id', $period['staffID'])->current();

	                    if(!($teacher instanceof User)) {
		                    throw new Exception_Schoolbox();
	                    }

                        $period['staffName'] = $teacher->formatName();
                        $period['room'] = trim($period['room']);
                    }
                    catch(Exception_Schoolbox $e) {
                        $cell['err'][] = 'Failed to read teacher details';
                    }

                    try {
                        $period['catID'] = $cMap->findOneByColumn('code', $period['code'])->getId();
                    }
                    catch (Exception_Schoolbox $e) {
                        $cell['err'][] = 'Failed to read category details';
                    }
                }
            }


            /* /REFACTOR */

            $r = array(
                'title'    => 'Timetable',
                'subtitle' => "Day $dayNum",
                'aside'    => '',
                'class'    => 'dashboard-timetable',
                'icon'     => 'clock',
                'content'  => $ttable,
            );

            return $r;

        }
        // if for some reason the mapper chucks a wobbly, just omit the timetable
        catch(Exception_Schoolbox $e) {
            return array('content'  => array());
        }
        // generic catch for Exceptions raised by Errors occurring. Minor errors and warnings should simply pass through
        catch(ErrorException $e) {
            return array('content'  => array());
        }
    }
}
