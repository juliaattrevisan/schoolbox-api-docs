<?php

/**
 * @author      Justin Clarke <justin.clarke@schoolbox.com.au>
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Canvas;

use Alaress\Web\Controller\Base;
use External\Canvas\Importer\Api;

class Controller extends Base
{
    /**
     * Controller constructor.
     *
     */
    public function __construct()
    {
    }


    /**
     * get Assignments
     *
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    public function getAssignments()
    {
        $assignments = [];
        //test data
//        $assignments[] = [
//            'id' => "assignment_987",
//            'title' => "Essay",
//            'start_at' => "2012-07-19T23:59:00-06:00",
//            'end_at' => "2012-07-19T23:59:00-06:00",
//            'description' => "<b>Write an essay. Whatever you want.</b>",
//            'context_code' => "course_123",
//            'workflow_state' => "published",
//            'url' => "https://example.com/api/v1/calendar_events/assignment_987",
//            'html_url' => "http://example.com/courses/123/assignments/987",
//            'all_day_date' => "2021-12-13",
//            'all_day' => true,
//            'created_at' => "2012-07-12T10:55:20-06:00",
//            'updated_at' => "2012-07-12T10:55:20-06:00",
//            'assignment' => null,
//            'assignment_overrides' => null,
//            'important_dates' => true
//        ];
//        $assignments[] = [
//            'id' => "assignment_987",
//            'title' => "Exam",
//            'start_at' => "2012-07-19T23:59:00-06:00",
//            'end_at' => "2036-07-19T23:59:00-06:00",
//            'description' => "<b>Write an essay. Whatever you want.</b>",
//            'context_code' => "English Unit 4",
//            'workflow_state' => "published",
//            'url' => "https://example.com/api/v1/calendar_events/assignment_987",
//            'html_url' => "https://ddg.gg",
//            'all_day_date' => "2021-12-13",
//            'all_day' => true,
//            'created_at' => "2012-07-12T10:55:20-06:00",
//            'updated_at' => "2012-07-12T10:55:20-06:00",
//            'assignment' => null,
//            'assignment_overrides' => null,
//            'important_dates' => true
//        ];


        $key = '1031~pbBe6G8P4R18723FsgmZsOX1KvOU2ka7GntkBt9rucM0Xl7znok2Pmsc6xh2QMu0';
        $host = 'https://canvas.newington.nsw.edu.au';
        $connection =  new Api($host, $key);

        //grab user
        $user = $this->getUser();
        $userId = urlencode($user->getExternalId());
        $userId = urlencode('sis_user_id:142898');

        if ($userId === null) {
            return $this->getResponse()->content->set($this->buildJsonOutput([
                                                                                 'error' => 'No External User ID',
                                                                             ]));
        }
        //get date 30 days in the future
        $endDate = gmdate("Y-m-d\TH:i:s", date_create('+1 Month')->getTimestamp());
        try {
        //get courses for user
        //get assignments for user
            $courses = $connection->retrieve('get', "/api/v1/users/${userId}/courses", [
            'state'             => 'available',
            'enrollment_state'  => 'active',
            ]);
            $coursesArr = [];
            $course10Count = 0;
            $courseCount = 0;
            foreach ($courses as $course) {
                $coursesArr[$course10Count][] = "course_{$course['id']}";
                $courseCount++;
                if ($courseCount % 10 === 0) {
                    $course10Count++;
                }
            }
        //split at groups of 10 courses
        //create arrays of a max 10 courses in each, then request the assignments from calendar using those events

            foreach ($coursesArr as $course10) {
                $courseParm = "context_codes[]=" . join('&context_codes[]=', $course10);
                $tempAssign = $connection->retrieve('get', "/api/v1/calendar_events?{$courseParm}&type=assignment&start_date=2021-12-01T00:53:51Z&end_date={$endDate}&as_user_id={$userId}", []);
                $assignments = array_merge($assignments, $tempAssign);
            }
        } catch (\RuntimeException $e) {
            return $this->getResponse()->content->set($this->buildJsonOutput([
                                                                          'error' => $e->getMessage(),
                                                                      ]));
        } catch (\DomainException $e) {
            return $this->getResponse()->content->set($this->buildJsonOutput([
                                                                          'error' => 'Unable to access server',
                                                                      ]));
        }
        //send as json to frontend
        return $this->getResponse()->content->set($this->buildJsonOutput([
            'assignments' => $assignments,
                                                                  ]));
    }
}
