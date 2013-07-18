<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
use Schoolbox\User;

class Facade_Component_SubjectGrades extends Facade_Component_Generic {

    /**
     * This should return an array of [title, aside, content]
     * @param User $user
     * @return array
     */
    public function render(User $user, $catID = 0) {

        // generate data
        // GENERATE RETURNED WORK
        $subHandler = new Submission();
        $work = array();
        $limit = 5;


        $allWork = array();

        $uid = $user->get('uid');

        $works = $subHandler->getRecentWork($uid, array($catID), RETURNED_WORK_TYPE);
        for ($i = 0; (is_array($works) && $i < count($works)); $i++) {
            $works[$i]['owner'] = $uid;
            $allWork[] = $works[$i];
        }

        usort($allWork, function($a, $b) {
            if ($a['relevant_date'] == $b['relevant_date'])
                return 0;
            else if ($a['relevant_date'] < $b['relevant_date'])
                return 1;
            return -1;
        });

        if(count($allWork) && is_array($allWork)) {
            $i = 0;
            foreach($allWork as $sub) {
                if($i >= $limit) break;
                $date = $sub['relevant_date']->format(Config::get('date_short'));
                $work[] = array(
                    'name' => $sub['name'],
                    'date' => $date,
                    'mark' => $sub['mark'],
                    'href'  => "/m/due_work/submission_box/display/{$sub['bid']}",
                );
                $i++;
            }
        }

        return array(
            'title'   => 'Recent Grades',
            'aside'   => '',
            'class'   => 'subject-grades',
            'icon'    => 'ok',
            'content' => $work,
        );

    }

}