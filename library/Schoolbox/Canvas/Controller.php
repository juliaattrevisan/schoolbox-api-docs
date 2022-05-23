<?php

/**
 * @author      Justin Clarke <justin.clarke@schoolbox.com.au>
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Canvas;

use Alaress\Web\Controller\Base;
use Crell\ApiProblem\ApiProblem;
use External\Canvas\Importer\Api;

class Controller extends Base
{

    private string $bearerToken;
    private string $canvasUrl;

    /**
     * Controller constructor.
     * @param string $canvasUrl
     * @param string $bearerToken
     */
    public function __construct(string $canvasUrl, string $bearerToken)
    {
        $this->canvasUrl    = $canvasUrl;
        $this->bearerToken  = $bearerToken;
    }


    /**
     * get Assignments
     *
     * @throws \Psr\Container\NotFoundExceptionInterface|\Psr\Container\ContainerExceptionInterface
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
//            'context_name' => "French Course",
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
//            'title' => "broken date",
//            'start_at' => "2012-07-19T23:59:00-06:00",
//            'end_at' => null,
//            'description' => "<b>Broken date, should still work...</b>",
//            'context_name' => "Broken",
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
//        $assignments[] = [
//            'id' => "assignment_987",
//            'title' => "Exam",
//            'start_at' => "2012-07-19T23:59:00-06:00",
//            'end_at' => "2036-07-19T23:59:00-06:00",
//            'description' => "<b>Write an essay. Whatever you want.</b>",
//            'context_name' => "English Unit 4",
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
//        foreach ($assignments as $id => $assignment) {
//            /**
//             * if the end_at is null, don't try to convert that to a date
//             * just leave as null, and get vue to show a nice empty state
//             */
//            if ($assignment['end_at'] !== null) {
//                /**
//                 * fix date format, from UTC ($assignment['end_at']) to localtime
//                 */
//                $date                       = \Alaress_Date_Format::newFromTimestamp(strtotime($assignment['end_at']));
//                $assignments[$id]['end_at'] = $date->getLocal();
//            }
//        }
//        return $this->getResponse()->content->set($this->buildJsonOutput([
//                                                                             'assignments' => $assignments,
//                                                                         ]));

        $connection =  new Api($this->canvasUrl, $this->bearerToken, 1);

        //grab user
        $user = $this->getUser();
        //userID format sis_user_id:sisID
        $userId = urlencode('sis_user_id:' . $user->getExternalId());
//        $userId = urlencode('sis_user_id:142898');

        if ($userId === 'sis_user_id:') {
            $problem = new ApiProblem("No External User ID");
            $problem->setStatus(400);
            return $this->getResponse()->content->set(
                $this->buildJsonOutput($problem->asArray())
            );
        }
        //get date 30 days in the future
        $endDate = gmdate("Y-m-d\TH:i:s", date_create('+2 Month')->getTimestamp());
        try {
        //get courses for user
        //get assignments for user
            /**
             * https://canvas.instructure.com/doc/api/courses.html#method.courses.user_index
             * grab all courses for the user, where the course is available, and the user is enrolled
             */
            $courses = $connection->retrieve('get', "/api/v1/users/${userId}/courses", [
            'state'             => 'available',
            'enrollment_state'  => 'active',
            ]);
            $coursesArr = [];
            $course10Count = 0;
            $courseCount = 0;
            /**
             * split the courses into groups of 10, as the canvas API has a limit of 10 context_codes (courses)
             */
            foreach ($courses as $course) {
                $coursesArr[$course10Count][] = "course_{$course['id']}";
                $courseCount++;
                if ($courseCount % 10 === 0) {
                    $course10Count++;
                }
            }

            /**
             * grab the list of assignments from each group of 10 courses
             * https://canvas.instructure.com/doc/api/calendar_events.html#method.calendar_events_api.user_index
             * specify:
             * type = assignment
             * start_date will default to today.
             * end_date 1 month in the future
             * list of courses as context_codes
             * specify the user using as_user_id (using the canvas api masquerade ability)
             * https://canvas.instructure.com/doc/api/file.masquerading.html
             */
            foreach ($coursesArr as $course10) {
                $courseParm = "context_codes[]=" . join('&context_codes[]=', $course10);
                $tempAssign = $connection->retrieve('get', "/api/v1/calendar_events?{$courseParm}&type=assignment&end_date={$endDate}&as_user_id={$userId}", []);
                $assignments = array_merge($assignments, $tempAssign);
            }

            //fix end_date for assignments to make it more user friendly...
            foreach ($assignments as $id => $assignment) {
                /**
                 * if the end_at is null, don't try to convert that to a date
                 * just leave as null, and get vue to show a nice empty state
                 */
                if ($assignment['end_at'] !== null) {
                    /**
                     * fix date format, from UTC ($assignment['end_at']) to localtime
                     */
                    $date                       = \Alaress_Date_Format::newFromTimestamp(strtotime($assignment['end_at']));
                    $assignments[$id]['end_at'] = $date->getLocal();
                }
            }
        } catch (\RuntimeException | \DomainException $e) {
            //catch both Runtime and Domain Exceptions
            $problem = new ApiProblem($e->getMessage());
            $problem->setStatus(400);
            $this->getResponse()->status->setCode(400);
            return $this->getResponse()->content->set(
                $this->buildJsonOutput($problem->asArray())
            );
        }
        //send as json to frontend
        return $this->getResponse()->content->set($this->buildJsonOutput([
            'assignments' => $assignments,
                                                                  ]));
    }
}
