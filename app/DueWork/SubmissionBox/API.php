<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class DueWork_SubmissionBox_API {

    public function Display($dueWorkID) {
        $response = new Response_HTTP();

        $boxHandler = new Submission();
        $box = $boxHandler->getByID($dueWorkID);


        $cMap = new GizaCategory_DataMapper();
        $cat = $cMap->read($box['category']);

        $catDetailsContent = array(
            'subject' => $cat->get(),
            'teacher' => $cat->get('teacher')->asArray(),
        );

        if($box['fid']) {
            $fileHandler = new File();
            $catDetailsContent['file'] = $fileHandler->getByID($box['fid']);
            $catDetailsContent['canDownload'] = (in_array(
                $_SESSION['env']['device'],
                array(ClientDevice::DEV_IPAD, ClientDevice::DEV_IPHONE)
            )) ? 'false' : 'true';
        }

        $due = strtotime($box['date']);

        $d = date('D, jS M', $due);

        if(date('Hi',$due) != '2359') {
            $d .= date(' h:ia', $due);
        }

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
                'description' => $box['description']
            )
        );

        $auth = new Authenticator();

        $allWork = $boxHandler->getSubmissionHistory($auth->getSessionUser()->get('uid'), $dueWorkID);

        $historyContent = array();

        if(is_array($allWork) && count($allWork)) {
            foreach($allWork as $currentWork) {
                $workDate = new Alaress_Date_Format($currentWork['date'], 'U');
                $historyContent[] = array(
                    'date'           => $workDate->getShort(),
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
