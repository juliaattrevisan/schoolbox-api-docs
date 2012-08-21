<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Facade_Component_SubjectDueWork extends Facade_Component_Generic {

    /**
     * This should return an array of [title, aside, content]
     * @param User $user
     * @return array
     */
    public function render(GizaUser $user, $catID = 0) {

        // GENERATE UPCOMING WORK
        $subHandler = new Submission();
        $work = array();

        $allWork = array();

        $uid = $user->get('uid');

        $works = $subHandler->getRecentWork($uid, array($catID), UPCOMING_WORK_TYPE);
        for ($i = 0; (is_array($works) && $i < count($works)); $i++) {
            $works[$i]['owner'] = $uid;
            $allWork[] = $works[$i];
        }

        usort($allWork, function($a, $b) {
            if ($a['relevant_date'] == $b['relevant_date'])
                return 0;
            else if ($a['relevant_date'] > $b['relevant_date'])
                return 1;
            return -1;
        });

        if(count($allWork) && is_array($allWork)) {
            foreach($allWork as $sub) {
                $date = $sub['relevant_date']->format(Config::get('date_short'));
                $work[] = array(
                    'name' => $sub['name'],
                    'date' => $date,
                    'href'  => "/m/due_work/submission_box/display/{$sub['bid']}",
                );
            }
        }

        return array(
            'title'   => 'Due Work',
            'aside'   => '',
            'class'   => 'subject-duework',
            'icon'    => 'work',
            'content' => $work,
        );
    }

}