<?php

use Schoolbox\External\Connector\Canvas\Base\Endpoint\Api;
use Schoolbox\Config\ConfigMapper;

/**
 * @author    Justin Clarke <justin.clarke@schoolbox.com.au>
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

class Component_Dashboard_CanvasAssignmentsController extends Component_Dashboard_ControllerAbstract
{
    /**
     * A new component will be added to the dashboard called. “Canvas Assignments”.
     * The component will query the REST API for ‘assignments’ and get events between the start of today and 30 days into the future. Note: that you can provide the external_id to the API by prepending it with “sis_user_id”
     * For each assignment the following information will be displayed
     * * Context Code
     * * Title
     * * End date
     * The title should link to the html_url provided in the response
     *
     * Calendar events for a user: https://canvas.instructure.com/doc/api/calendar_events.html#method.calendar_events_api.user_index filtered by type of assignment.
     */

    /**
     * returned list of assignments
     */
    private array $assignments;

    /**
     */
    public function execute()
    {
        /**
         * connect to canvas
         *
         * get calendar events for the current user
         *
         * filter by specified type?
         *
         * temp store the assignments
         */


        //connect to canvas
        // Check Canvas URL config
        $key = '1031~pbBe6G8P4R18723FsgmZsOX1KvOU2ka7GntkBt9rucM0Xl7znok2Pmsc6xh2QMu0';
        $host = 'https://canvas.newington.nsw.edu.au';
//        if (
//            !$this->getConfigLoader()->get(ConfigMapper::_CANVAS_URL) ||
//            !$this->getConfigLoader()->get(ConfigMapper::_CANVAS_API_KEY)
//        ) {
//            return false;
//        }

        $connection =  new Api(
            $host, $key
        );

        //get userId
        $userId = $this->getUser()->getExternalId();
        $userId = '124933';
        $userId = '1213412';

        if ($userId === null) {
            return false;
        }
        //get date 30 days in the future
        $endDate = gmdate("Y-m-d\TH:i:s", date_create('+3 Month')->getTimestamp());


        /** test assignments for the user */
        $this->assignments = [];
        $this->assignments[] = [
            'id' => "assignment_987",
            'title' => "Essay",
            'start_at' => "2012-07-19T23:59:00-06:00",
            'end_at' => "2012-07-19T23:59:00-06:00",
            'description' => "<b>Write an essay. Whatever you want.</b>",
            'context_code' => "course_123",
            'workflow_state' => "published",
            'url' => "https://example.com/api/v1/calendar_events/assignment_987",
            'html_url' => "http://example.com/courses/123/assignments/987",
            'all_day_date' => "2021-12-13",
            'all_day' => true,
            'created_at' => "2012-07-12T10:55:20-06:00",
            'updated_at' => "2012-07-12T10:55:20-06:00",
            'assignment' => null,
            'assignment_overrides' => null,
            'important_dates' => true
        ];
        $this->assignments[] = [
            'id' => "assignment_987",
            'title' => "Exam",
            'start_at' => "2012-07-19T23:59:00-06:00",
            'end_at' => "2036-07-19T23:59:00-06:00",
            'description' => "<b>Write an essay. Whatever you want.</b>",
            'context_code' => "English Unit 4",
            'workflow_state' => "published",
            'url' => "https://example.com/api/v1/calendar_events/assignment_987",
            'html_url' => "https://ddg.gg",
            'all_day_date' => "2021-12-13",
            'all_day' => true,
            'created_at' => "2012-07-12T10:55:20-06:00",
            'updated_at' => "2012-07-12T10:55:20-06:00",
            'assignment' => null,
            'assignment_overrides' => null,
            'important_dates' => true
        ];
        $this->assignments[] = [

            "id" => 0,
            "title" => "",
            "start_at" => "2021-10-01",
            "end_at" => "2021-11-29",
            "description" => "",
            "location_name" => "",
            "location_address" => "",
            "context_code" => "",
            "effective_context_code" => "",
            "context_name" => "",
            "all_context_codes" => "",
            "workflow_state" => "",
            "hidden" => false,
            "parent_event_id" => 0,
            "child_events_count" => 0,
            "child_events" => [
                0
            ],
            "url" => "",
            "html_url" => "",
            "all_day_date" => "datetime",
            "all_day" => false,
            "created_at" => "datetime",
            "updated_at" => "datetime",
            "appointment_group_id" => 0,
            "appointment_group_url" => "",
            "own_reservation" => false,
            "reserve_url" => "",
            "reserved" => false,
            "participant_type" => "",
            "participants_per_appointment" => 0,
            "available_slots" => 0,
            "user" => "",
            "group" => "",
            "important_dates" => false

        ];
        $this->assignments[] = [
            "title" => 'Assignment -deleted',
            "context_code" => 'English_34',
            "html_url" => 'https://localhost',
            "end_at" => "2021-11-29",
            'workflow_state' => "deleted",
        ];
        $this->assignments[] = [
            "title" => 'Assignment deleted',
            "context_code" => 'English_34',
            "html_url" => 'https://localhost',
            "end_at" => "2021-11-29",
            'workflow_state' => "deleted",
        ];
        $this->assignments[] = [
            "title" => 'Assignment for english - book review',
            "context_code" => 'English_34',
            "html_url" => 'https://localhost',
            "end_at" => "2021-11-29",
            'workflow_state' => "published",
        ];
        $this->assignments[] = [
            "title" => 'Assignment',
            "context_code" => 'English_34',
            "html_url" => 'https://localhost',
            "end_at" => "2021-11-29",
            'workflow_state' => "published",
        ];
        $this->assignments[] = [
            "title" => 'Assignment',
            "context_code" => 'English_34',
            "html_url" => 'https://localhost',
            "end_at" => "2021-11-29",
            'workflow_state' => "published",
        ];$this->assignments[] = [
            "title" => 'Assignment',
            "context_code" => 'English_34',
            "html_url" => 'https://localhost',
            "end_at" => "2021-11-29",
            'workflow_state' => "published",
        ];
        $this->assignments[] = [
            "title" => 'Assignment',
            "context_code" => 'English_34',
            "html_url" => 'https://localhost',
            "end_at" => "2021-11-29",
            'workflow_state' => "published",
        ];//10?
//        $this->assignments[] = [
//            "title" => 'Assignment',
//            "context_code" => 'English_34',
//            "html_url" => 'https://localhost',
//            "end_at" => "2021-11-29",
//            'workflow_state' => "published",
//        ];
//        $this->assignments[] = [
//            "title" => 'Assignment',
//            "context_code" => 'English_34',
//            "html_url" => 'https://localhost',
//            "end_at" => "2021-11-29",
//            'workflow_state' => "published",
//        ];
//        $this->assignments[] = [
//            "title" => 'Assignment',
//            "context_code" => 'English_34',
//            "html_url" => 'https://localhost',
//            "end_at" => "2021-11-29",
//            'workflow_state' => "published",
//        ];
//        $this->assignments[] = [
//            "title" => 'Assignment',
//            "context_code" => 'English_34',
//            "html_url" => 'https://localhost',
//            "end_at" => "2021-11-29",
//            'workflow_state' => "published",
//        ];
//        $this->assignments[] = [
//            "title" => 'Assignment',
//            "context_code" => 'English_34',
//            "html_url" => 'https://localhost',
//            "end_at" => "2021-11-29",
//            'workflow_state' => "published",
//        ];//15
//        $this->assignments[] = [
//            "title" => 'Assignment',
//            "context_code" => 'English_34',
//            "html_url" => 'https://localhost',
//            "end_at" => "2021-11-29",
//            'workflow_state' => "published",
//        ];
//        $this->assignments[] = [
//            "title" => 'Assignment',
//            "context_code" => 'English_34',
//            "html_url" => 'https://localhost',
//            "end_at" => "2021-11-29",
//            'workflow_state' => "published",
//        ];
//        $this->assignments[] = [
//            "title" => 'Assignment',
//            "context_code" => 'English_34',
//            "html_url" => 'https://localhost',
//            "end_at" => "2021-11-29",
//            'workflow_state' => "published",
//        ];$this->assignments[] = [
//            "title" => 'Assignment',
//            "context_code" => 'English_34',
//            "html_url" => 'https://localhost',
//            "end_at" => "2021-11-29",
//            'workflow_state' => "published",
//        ];
//        $this->assignments[] = [
//            "title" => 'Assignment',
//            "context_code" => 'English_34',
//            "html_url" => 'https://localhost',
//            "end_at" => "2021-11-29",
//            'workflow_state' => "published",
//        ];//20

        //get calendar events for the current user
        try {
            /**
             * canvas API being what it is, I apparently need to specify the courses when doing the API call for the assignments
             * so lets grab a list of the courses the user is in, then build / send the get assignments request, with a loop of 10 courses a time. yay for loops
             */
            $this->assignments = [];
            $courses = $connection->retrieveDocument('get', "/api/v1/users/${userId}/courses", [
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
                $params = [
//                $assignments = $connection->retrieveDocument('get', "/api/v1/users/${userId}/calendar_events", [
//                    'end_date'          => $endDate,
                    'all_events'        => 'true',
//                    'context_codes[]'   =>  'course_1601419',
                    'type'              => 'assignment',
                    'as_user_id'        => $userId,
                ];
                $courseParm = join('&context_codes[]=', $course10);
                $paramsJoin = join('&', $params);
                $assignments = $connection->retrieveDocument('get', "/api/v1/calendar_events?context_codes[]={$courseParm}&type=assignment&all_events=true&as_user_id={$userId}", []);
                $this->assignments = array_merge($this->assignments, $assignments);
            }
        } catch (\Throwable $e) {
            $this->assignments = [];
        }

        return true;
    }

    /**
     */
    public function renderTemplate($templateHandle)
    {
        $assign = $this->assignments;
        /**
         * grab temp assignments
         *
         * send to template to display in the correct format.
         */
        return $this->renderTwigTemplate(
            $templateHandle,
            'component/dashboard/canvasAssignments.twig',
            [
                'componentInstanceId' => $this->getInstance()->getId(),
                'externalType'        => $this->getConfigLoader()->get('external_type'),
                'assignments'    => $assign,
            ],
            false,
            false,
            false,
            false
        );
    }
}
