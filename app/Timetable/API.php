<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Timetable_API {

    private $data_cache = array();

    public function Display() {

        $response = new Response_HTTP();

        try {
            $auth = new Authenticator();
            $user = $auth->getSessionUser();

            $timetableHandler = new External_Timetable();

            $eUser = new External_User();
            $eUser->getCurrentSemester();

            $campus = $user->get('campus_id');

            $today = $timetableHandler->getDayNumber($campus);

            $tableDefinition = $timetableHandler->getTimetableDefinition($campus, 1);

            if(!is_array($tableDefinition) || !count($tableDefinition) || $_SESSION['timetable'] != 1) {
                $r = array(
                    'title'   => 'Timetable',
                    'aside'   => '',
                    'class'   => 'timetable wide',
                    'icon'    => '',
                    'content' => array(),
                );

                $response->setPage('title','Timetable');
                $response->setResponse('articles', array($r));

                return $response;
            }

            $now = time();

            // get the current period (or next if we're not in one)

            // set $i to the current period index based on time
            for($i = 0; $i < $tableDefinition['periodCount']; $i++) {
                if(strtotime($tableDefinition['end'][$i]) > $now) {
                    break;
                }
            }

            $highlightedRow = -1;
            //
            // If $i didn't wrap all the way around, then set the highlighed row.
            // If it did, then don't highlight any row, and don't wrap to the next day.
            // (same behavior as desktop site)
            if ($i < $tableDefinition['periodCount']) {
                // row * count(rows) + sequence
                $hIndex = $i % $tableDefinition['periodCount'];
                $highlightedRow = ($tableDefinition['number'][$hIndex]) * $tableDefinition['periodCount']
                    + $tableDefinition['sequence'][$hIndex];
            }

            // retrieve from handler -_-
            $table = $timetableHandler->getFullTimetable(
                $user->get('synergy_id'),
                $user->get('uid'),
                $campus
            );

            // man-handle the data
            $uMap = new GizaUser_DataMapper();
            $cMap = new GizaCategory_DataMapper();

            foreach($table as &$period) {
                if(($period['periodNumber'] * $tableDefinition['periodCount'] + $period['periodSequence']) == $highlightedRow) {
                    $period['highlight'] = '1';
                }
                foreach($period['day'] as $day => &$class) {
                    if($day == $today) {
                        $class['today'] = '1';
                    }
                    if(isset($class['staffID'])) {
                        try {
                            $user = $this->readData($uMap, $class['staffID']);
                            $class['teacherName'] = $user->getName();
                            $class['room'] = trim($class['room']);
                        }
                        catch (Exception_Schoolbox $e) {
                            $class['err'][] = "Failed to read staff with `synergy_id` = '{$class['staffID']}': {$e->getErrorMessage()}";
                        }

                        try {
                            $response->setResponse('debug', array($class));
                            $class['catID'] = $this->readData($cMap, $class['code'])->get('id');
                        }
                        catch (Exception_Schoolbox $e) {
                            $class['err'][] = "Failed to read category with `categoryCode`.`code` = '{$class['code']}': {$e->getErrorMessage()}";
                        }
                    }
                }
				unset($class);
            }
			unset($period);

            $r = array(
                'title'   => 'Timetable',
                'aside'   => '',
                'class'   => 'timetable wide',
                'icon'    => '',
                'content' => $table,
            );

            $response->setPage('title','Timetable');
            $response->setResponse('articles', array($r));

            return $response;
        }

        // if the datamapper fails to retrieve correctly for any reason, recover and advise timetable is unavailable
        catch(Exception_Schoolbox $e) {
            return $this->generateErrorResponse($response);
        }
        // if something entirely unexpected happens, like tables on the db are missing
        catch(ErrorException $e) {
            return $this->generateErrorResponse($response);
        }
    }

    /**
     * @param Generic_DataMapper $mapper
     * @param $id
     * @return Generic_Object|null
     */
    private function readData($mapper, $id) {
        // read user from cache or db
        if($mapper instanceof GizaUser_DataMapper) {
            if(!isset($this->data_cache['user'][$id])) {
                $this->data_cache['user'][$id] = $mapper->read(array('synergy_id' => $id))->get();
            }
            return $this->data_cache['user'][$id];
        }
        // read category from cache or db
        elseif($mapper instanceof GizaCategory_DataMapper) {
            if(!isset($this->data_cache['cat'][$id])) {
                $this->data_cache['cat'][$id] = $mapper->read(array('categoryCode.code' => $id))->get();
            }
            return $this->data_cache['cat'][$id];
        }
        // fail
        return null;
    }

    private function generateErrorResponse(Response_HTTP $response) {
        $r = array(
            'title'   => 'Timetable',
            'aside'   => '',
            'class'   => 'timetable wide',
            'icon'    => '',
            'content' => array(),
        );

        $response->setPage('title','Timetable');
        $response->setResponse('articles', array($r));

        return $response;
    }
}
