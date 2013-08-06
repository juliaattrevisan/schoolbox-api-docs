<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Mobile\Facade\Component;

use Alaress_Date_Relative;
use Scrub;
use Submission;
use Schoolbox\User;

class SubjectDueWork extends Generic {

    /**
     * This should return an array of [title, aside, content]
     * @param User $user
     * @return array
     */
    public function render(User $user, $catID = 0) {

        // GENERATE UPCOMING WORK
        $subHandler = new Submission();
        $work = array();
        $now = time();

        $uid = $user->getId();

        $upcoming = $subHandler->getUpcomingWorkAndAssessmentsByCategory($uid, $catID);

        if(count($upcoming) && is_array($upcoming)) {

            foreach($upcoming as &$item) {
                $invalid = false;

                if($item['kind'] == 0) {
                    // duework
                    if($item['submittable']) {
                        if($item['multi_submit']) {
                            // if unsubmitted, hide if LATE date passed
                            if(!$item['submit_count'] && $item['late_date'] < $now) {
                                $invalid = 'a';
                            }
                            // if submitted, hide if DUE date passed
                            elseif($item['submit_count'] && $item['date'] < $now) {
                                $invalid = 'b';
                            }
                        }
                        else {
                            // single submit: hide if completed or DUE date in past
                            if($item['date'] < $now || $item['submit_count']) {
                                $invalid = 'c';
                            }
                        }
                    }
                    else {
                        // no submissions: hide if DUE date in past
                        if($item['date'] < $now) {
                            $invalid = 'd';
                        }
                    }
                }
                else {
                    // assess
                    if($item['max_attempts'] == 0) {
                        // 0 max attempts is unlimited: hide if FINISH in past
                        if($item['date'] < $now) {
                            $invalid = 'e';
                        }
                    }
                    elseif($item['attempt_count'] >= $item['max_attempts']) {
                        // if limited attempts, hide if limit has been reached
                        $invalid = 'f';
                    }
                    elseif($item['date'] < $now) {
                        $invalid = 'g';
                    }
                }

                // skip if invalid for some reason
                if($invalid) {
                    continue;
                }

                $adr = new Alaress_Date_Relative($item['date'], 'y-m-d');

                // how many days left, 0 <= x <= 10
                $item['urgency'] = max(0, min(10, (int)(($adr->getUnixTimestamp() - $now) / 86400)));
                $item['date_relative'] = $adr->getRelativeTime();

                if($item['kind'] == 0) {
                    $item['href'] = '/m/due_work/submission_box/display/' . $item['id'];
                }

                $work[] = $item;
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