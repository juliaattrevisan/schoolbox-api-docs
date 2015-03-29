<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
namespace Schoolbox\Mobile\Timetable;

use Schoolbox\Mobile\Generic;
use Schoolbox\Resource\Folder\Mapper as CategoryMapper;
use Response_HTTP;
use Config;
use Exception_Schoolbox;
use ErrorException;
use External_Timetable;
use ClientDevice;
use Schoolbox\Role;
use Scrub;
use Authenticator;

use Schoolbox\UserMapper;

class API {

    private $data_cache = array();

    public function Display($userId = 0) {

        $response = new Response_HTTP();

        try {
            $auth = new Authenticator();
	        $gHandler = new \Group();

	        // set up the viewing and target users
	        $sessionUser = $auth->getSessionUser();
	        $targetUser = $userId ? UserMapper::getInstance()->findById($userId) : $sessionUser;

	        // you can view a timetable if ANY of the following conditions are true
	        //  - it is your own and you are not a parent (they don't have them)
	        //  - you are a super
	        //  - you are staff and it is a student's
	        //  - you are a parent and it is your child's

	        if(!(
	            ($sessionUser->getId() == $targetUser->getId())
		        || $sessionUser->isSuperuser()
		        || ($sessionUser->getRole()->getType() == Role::STAFF && $targetUser->getRole()->getType() == Role::STUDENT)
		        || $gHandler->isParentOfChild($targetUser->getId())
	        )) {
				// deny access
		        return $this->generateErrorResponse(new Response_HTTP(Response_HTTP::HTTP_FORBIDDEN));
	        }

	        // parents don't have roles, 404.
	        if($targetUser->getRole()->getType() == Role::PARENT) {
		        return $this->generateErrorResponse(new Response_HTTP(Response_HTTP::HTTP_NOT_FOUND));
	        }

            $timetableHandler = new External_Timetable();

	        // most likely, default campus will be populated
	        if($targetUser->getDefaultCampus()) {
		        $campus = $targetUser->getDefaultCampus();
	        }
	        // if not, attempt to retrieve the first campus from their list of campuses (campi?)
	        elseif($targetUser->getCampusCollection()->count()) {
		        $campus = $targetUser->getCampusCollection()->first();
	        }
	        // if user not in any campus, cannot generate timetable: 404.
	        else {
				return $this->generateErrorResponse(new Response_HTTP(Response_HTTP::HTTP_NOT_FOUND));
	        }

            $today = $timetableHandler->getDayNumber($campus);

            $tableDefinition = $timetableHandler->getTimetableDefinition($targetUser->getExternalId(), $campus, $today);

            if(!is_array($tableDefinition) || !count($tableDefinition)) {
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
	            $targetUser->getExternalId(),
	            $targetUser->getId(),
                $campus
            );

	        /**
	         * man-handle the data
	         * @var UserMapper $uMap
	         * @var CategoryMapper $cMap
	         */
	        $uMap = UserMapper::getInstance();
            $cMap = CategoryMapper::getInstance();

	        $colourList = \Timetable_ColourMapper::getInstance()->findAll();
	        $subjectColourMap = array();
	        $colourIndex = 0;

	        foreach($table as &$period) {
                if (($period['periodNumber'] * $tableDefinition['periodCount'] + $period['periodSequence']) == $highlightedRow) {
                    $period['highlight'] = '1';
                }

                foreach ($period['day'] as $day => &$classes) {

                    foreach ($classes as &$class) {
                        if ($day == $today) {
                            $class['today'] = '1';
                        }

                        if (count($class) == 0) {
                            continue;
                        }

                        if ($class['code'] && !array_key_exists($class['code'], $subjectColourMap)) {
                            if (!$colourList->offsetExists($colourIndex)) {
                                $colourIndex = 0;
                            }
                            $subjectColourMap[$class['code']] = $colourList->offsetGet($colourIndex)
                                                                           ->getHexColour();
                            $colourIndex++;
                        }
                        $class['colour'] = '#' . $subjectColourMap[$class['code']];
                        $class['room']   = trim($class['room']);

                        if (isset($class['staffID'])) {
                            try {
                                $user                 = $this->readData($uMap, $class['staffID']);
                                $class['teacherName'] = $user->formatName();
                            } catch (Exception_Schoolbox $e) {
                                $class['err'][] = "Failed to read staff with `synergy_id` = '{$class['staffID']}': {$e->getErrorMessage()}";
                            } catch (\Exception $e) {
                                $class['err'][] = "Failed to read staff with `synergy_id` = '{$class['staffID']}'";
                            }
                        }

                        try {
                            $class['catID'] = $this->readData($cMap, $class['code'])
                                                   ->getId();
                        } catch (Exception_Schoolbox $e) {
                            $class['err'][] = "Failed to read category with `categoryCode`.`code` = '{$class['code']}': {$e->getErrorMessage()}";
                        } catch (\Exception $e) {
                            $class['err'][] = "Failed to read category with `categoryCode`.`code` = '{$class['code']}'";
                        }
                        $response->setResponse('debug', array($class));
                    }
                    unset($class);
                }
                unset($classes);
            }
			unset($period);

	        $forUser = $sessionUser->getId() == $targetUser->getId() ? '' : " &raquo; " . $targetUser->formatName();

            $r = array(
                'title'   => 'Timetable',
                'aside'   => '',
                'class'   => 'timetable wide',
                'icon'    => '',
                'content' => array_values($table),
            );

            $response->setPage('title', "Timetable $forUser");
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
     * @param \Schoolbox\Mapper $mapper
     * @param $id
     * @return \Schoolbox\User|\Schoolbox\Resource\Category
     */
    private function readData($mapper, $id) {
        // read user from cache or db
        if($mapper instanceof UserMapper) {
            if(!isset($this->data_cache['user'][$id])) {
                $this->data_cache['user'][$id] = $mapper->findOneByColumn('synergy_id', $id);
            }
            return $this->data_cache['user'][$id];
        }
        // read category from cache or db
        elseif($mapper instanceof CategoryMapper) {
            if(!isset($this->data_cache['cat'][$id])) {
                // Need to search on category code, not category.
                $this->data_cache['cat'][$id] = $mapper->findByCode($id);
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
