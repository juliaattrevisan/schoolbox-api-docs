<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Mobile\Facade\Component;

use Alaress_Date_Format;
use Schoolbox\Resource\CategoryMapper;
use External_Timetable;
use Exception_Schoolbox;
use ErrorException;
use Schoolbox\User;
use Schoolbox\UserMapper;

class CurrentPeriod extends Generic {

    /**
     * @param User $user
     * @return array
     */
    public function render(User $user) {

        if($_SESSION['timetable'] == 0) {
            // return an array without title set, which will prevent the article rendering
            // *content needs to remain set
            return array('content'  => array());
        }

        try {

            /* REFACTOR */
            $timetableHandler = new External_Timetable();

            // Show the default campus timetable -_-
            // I guess this means we won't be able to get rid of it after all
            // (or at least until mobile gets deprecated!)
            $campus = $user->getDefaultCampus();

            /*
            $dayNum = 4;
            $now = mktime(19,0,0);
            */
            $dayNum = $timetableHandler->getDayNumber($campus);
            $now = time();

            $def = $timetableHandler->getTimetableDefinition($user->getExternalId(), $campus, $dayNum);

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

            $current = $timetableHandler->getPeriodNumber($campus);

            $ttable = array();

            $currentFlag = false;
            for($i = 0; $i < count($def['end']); $i++) {
                $ttable[$i] = $todayDef[$i];
                if (!$currentFlag && $ttable[$i]['periodNumber'] == $current['PeriodNumber'] && $ttable[$i]['periodSequence'] == $current['PeriodNumberSeq']) {
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

	        $colourList = \Timetable_ColourMapper::getInstance()->findAll();
	        $subjectColourMap = array();
	        $colourIndex = 0;

            foreach($ttable as &$period) {
                if ($period['code'] && !array_key_exists($period['code'], $subjectColourMap)) {
                    if (!$colourList->offsetExists($colourIndex)) {
                        $colourIndex = 0;
                    }
                    $subjectColourMap[$period['code']] = $colourList->offsetGet($colourIndex)->getHexColour();
                    $colourIndex++;
                }
                $period['colour'] = '#' . $subjectColourMap[$period['code']];
                $period['room']   = trim($period['room']);
                if (isset($period['staffID'])) {
                    try {
                        /** @var User $teacher */
                        $teacher = $uMap->findOneByColumn('synergy_id', $period['staffID']);
                        $period['staffName'] = $teacher->formatName();
                    }
                    catch (\Exception $e) {
                        $cell['err'][] = 'Failed to read teacher details';
                    }
                }
                try {
                    $period['catID'] = $cMap->findByCode($period['code'])->getId();
                }
                catch (\Exception $e) {
                    $cell['err'][] = 'Failed to read category details';
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
