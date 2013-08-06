<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Mobile\DueWork\SubmissionBox;

use Schoolbox\Resource\Category;
use Schoolbox\Resource\CategoryMapper;
use Response_HTTP;
use Cfg;
use Submission;
use Alaress_Date_Relative;
use User;
use Group;
use File;
use ClientDevice;
use External_Class;
use Scrub;
use Authenticator;

class DueWork_SubmissionBox_API {

    public function Display($dueWorkID) {
        $response = new Response_HTTP();

        $boxHandler = new Submission();
        $box = $boxHandler->getByID($dueWorkID);
        if (!$box) {
            $response->setStatusCode(Response_HTTP::HTTP_NOT_FOUND);
            $response->setPage('title', 'Submission Not Found');
            $response->setResponse('articles', array('Error' => array(
                'title' => 'Submission Not Found',
                'icon' => '',
                'class' => 'wide no-title',
                'content' => array(
                    'reason' => 'Sorry, the submission requested could not be found.'
                )
            )));
            return $response;
        }

	    /**
	     * @var $cMap CategoryMapper
	     * @var Category $cat
	     */
	    $cMap = CategoryMapper::getInstance();
        $cat = $cMap->findById($box['category']);

		// find out who teaches this subject (if it is, in fact, a subject)
		$teachers = array();
	    $userHandler = new User();
	    $groupHandler = new Group();

	    if ($cat->getCodes()->count() && Cfg::get('external_enable')) {

		    $externalClassHandler = new External_Class();
		    $listSyns = $externalClassHandler->getTeacherName($cat->getCodes()->first()->getCode());
		    $staffGroups = $groupHandler->getStaffGroupTypeIds();

		    if (is_array($listSyns)) {
			    foreach($listSyns as $synid) {
				    $teacher = $userHandler->getBySynergyAndGroup($synid, $staffGroups);
				    $meta = $userHandler->getByID($teacher['uid']);
				    if (is_array($teacher)) {
					    $teachers[] = $userHandler->formatName($meta);
				    }
			    }
		    }

	    }

        $catDetailsContent = array(
            'subject' => $cat,
            'teacher' => $teachers,
        );

        if($box['fid']) {
            $fileHandler = new File();
            $catDetailsContent['file'] = $fileHandler->getByID($box['fid']);
            $catDetailsContent['canDownload'] = (in_array(
                $_SESSION['env']['device'],
                array(ClientDevice::DEV_IPAD, ClientDevice::DEV_IPHONE),
                true
            )) ? 'false' : 'true';
        }

        $due = new Alaress_Date_Relative($box['date'], 'y-m-d');

        $d = $due->getUsingFormat('jS M');
        $rel = $due->getRelativeTime();

        if($due->getUsingFormat('Hi') != '2359') {
            $d .= $due->getUsingFormat(' h:ia');
        }
        $d = "$rel ($d)";

        $catDetails = array(
            'title'   => 'Due ' . $d,
            'aside'   => '',
            'class'   => '',
            'icon'    => 'clock',
            'content' => $catDetailsContent
        );

        $description = array(
            'title'   => 'Description',
            'aside'   => '',
            'class'   => '',
            'icon'    => 'subjects',
            'content' => array(
                'description' => Scrub::html($box['description'])
            )
        );

        $auth = new Authenticator();

        $allWork = $boxHandler->getSubmissionHistory($auth->getSessionUser()->getId(), $dueWorkID);

        $historyContent = array();

        if(is_array($allWork) && count($allWork)) {
            foreach($allWork as $currentWork) {
                $workDate = new Alaress_Date_Relative($currentWork['date'], 'U');
                $historyContent[] = array(
                    'date'           => $workDate->getShort(),
                    'date_relative'  => $workDate->getRelativeTime(),
                    'mark'           => $currentWork['mark'],
                    'comment'        => $currentWork['comment'],
                    'file_id'        => $currentWork['fid'],
                    'submit_file_id' => $currentWork['sid'],
                    'return_file_id' => $currentWork['rid'],
                );
            }
        }

        $history = array(
            'title'   => 'Submission History',
            'aside'   => '',
            'class'   => '',
            'icon'    => 'work',
            'content' => array(
                'history' => $historyContent
            )
        );

        $articles = array($catDetails, $description, $history);

        $response->setPage('title', $box['name']);
        $response->setResponse('articles', $articles);

        return $response;
    }

}
