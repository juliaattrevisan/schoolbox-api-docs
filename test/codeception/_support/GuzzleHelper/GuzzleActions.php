<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace GuzzleHelper;

use Alaress\Redis;
use GuzzleHttp\Exception\GuzzleException;
use Schoolbox;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Psr7\Response;
use Schoolbox\Comms\News\Querystring;
use Schoolbox\Mapper\Exception;
use Schoolbox\Realtime\MessageList\ListFactory;
use Schoolbox\Realtime\Notification\Decorator;
use Schoolbox\User;

const VAGRANT_DOMAIN = 'vagrant.dev.schoolbox.cloud';


trait GuzzleActions
{
    /** @var Client $client */
    public $client;

    /** @var CookieJar $client_cookie_jar */
    public $client_cookie_jar;

    public $guzzle_headers=[];

    public $redirect_settings;

    /**
     * initGuzzleClient initializes the HTTP Guzzle client used to send off curl requests.
     */
    function initGuzzleClient()
    {
        $this->client = new Client(['base_uri' => 'https://'.VAGRANT_DOMAIN, 'timeout' => 5.0, 'cookies' => true]);
        $this->guzzle_headers=[ 'Host' => VAGRANT_DOMAIN,
            'Content-Type'=>'application/x-www-form-urlencoded; charset=UTF-8',
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8'];
        $this->redirect_settings=[
            'max' => 5,
            'referer' => true,
            'protocols' => ['http', 'https'],
            'track_redirects' => true
        ];
        $this->client_cookie_jar = new CookieJar();
    }

    /**
     * waitForUserMessage waits for a message in the redis server
     *
     * @param User $user
     * @param $time_created
     * @param int $timeout in seconds
     * @param string $filter addition filtering on events by title
     *
     * @return Schoolbox\Pastoral\Model
     */
    function waitForUserMessage($user, $time_created, $filter, $timeout=10){
        $factory = new ListFactory(INSTANCE_ID, Redis::getClientInstance());

        // load the data into your hat, and pull out a rabbit
        $decorator = new Decorator();

        $sleep_counter = $timeout;
        while ($sleep_counter > 0)
        {
            $factory->unread($user->getId())->fetchIntoBundle($decorator);
            $unread = $decorator->renderAsSitePageArray();

            if ($unread)
            {
                $latest_message['time']=0;
                $latest_message['title']='';
                foreach($unread as $id => $notification) {
                    if ($latest_message['time']<$notification['time'])
                    {
                        if ($filter) {
                            if (strpos($notification['title'], $filter) !== false)
                            {
                                $latest_message = $notification;
                            }
                        }
                    }
                }

                if ($latest_message['time']>=$time_created)
                {
                    return $latest_message;
                }
            }
            sleep(1);
            $sleep_counter--;
        }
        return NULL;
    }

    /**
     * loginUser fire off Curl to login user.
     *
     * @param string $username
     * @param string $password
     * @param bool $redirect if the request should follow the redirect.
     *
     * @throws GuzzleException
     *
     * @return array
     */
    public function loginUser($username, $password, $redirect=true)
    {
        if (!isset($this->client))
        {
            $this->initGuzzleClient();
        }

        $this->guzzle_headers['Referer'] = 'https://'.VAGRANT_DOMAIN.'/login/';

        $page='/login/';
        $url = 'https://'.VAGRANT_DOMAIN.$page;
        $data =[
            'page' => '/login/',
            'username' => $username,
            'password' => $password,
            'Submit'=> 'Login'
        ];

        if ($redirect) {
            $allow_redirects = $this->redirect_settings;
        } else {
            $allow_redirects=false;
        }

        return $this->client->request('POST', $url, [
            'headers'=>$this->guzzle_headers,
            'query' =>'page=/login/',
            'form_params' => $data,
            'cookies' => $this->client_cookie_jar,
            'allow_redirects' => $allow_redirects
        ]);
    }

    /**
     * loginForgotPasswordUsername fire off Curl a forgot password request using a username
     *
     * @param string $username
     * @param bool $redirect if the request should follow the redirect.
     *
     * @throws GuzzleException
     *
     * @return array
     */
    public function loginForgotPasswordUsername($username, $redirect=true)
    {
        if (!isset($this->client))
        {
            $this->initGuzzleClient();
        }

        $this->guzzle_headers['Referer'] = 'https://'.VAGRANT_DOMAIN.'/passwordReset';

        $page='/passwordReset/createRequest';
        $url = 'https://'.VAGRANT_DOMAIN.$page;
        $data =[
            'email' => $username
        ];

        if ($redirect) {
            $allow_redirects = $this->redirect_settings;
        } else {
            $allow_redirects=false;
        }

        return $this->client->request('POST', $url, [
            'headers'=>$this->guzzle_headers,
            'form_params' => $data,
            'cookies' => $this->client_cookie_jar,
            'allow_redirects' => $allow_redirects
        ]);
    }

    /**
     * loginForgotPasswordEmail fire off Curl a forgot password request using an email
     *
     * @param string $email
     *
     * @throws GuzzleException
     *
     * @return array
     */
    public function loginForgotPasswordEmail($email)
    {
        return $this->loginForgotPasswordUsername($email);
    }

    /**
     * createNewCourseSpec fire off Curl to create a new course spec.
     *
     * @param string $name
     * @param Schoolbox\Resource\Folder\Model $parent_folder
     *
     * @throws GuzzleException
     *
     * @return array
     */
    public function createNewCourseSpec($name, $parent_folder)
    {
        if (!isset($this->client))
        {
            $this->initGuzzleClient();
        }

        $this->guzzle_headers['Referer'] = 'https://'.VAGRANT_DOMAIN.'/course/new';

        $page='/course/new';
        $url = 'https://'.VAGRANT_DOMAIN.$page;
        $data =[
            'name' => $name,
            'parent' => $parent_folder->getId(),
            'curriculumCodes' => [''],
            'part1'=> 1
        ];

        $allow_redirects = $this->redirect_settings;

        return $this->client->request('POST', $url, [
            'headers' => $this->guzzle_headers,
            'allow_redirects' => $allow_redirects,
            'cookies'=>$this->client_cookie_jar,
            'form_params'=>$data
        ]);
    }

    /**
     * createNewUnitSpec fire off Curl to create a new unit spec
     *
     * @param string $name
     * @param \Schoolbox\Learning\Course\DataModel\Specification\Model $course
     *
     * @throws GuzzleException
     *
     * @return array
     */
    public function createNewUnitSpec($name, $course)
    {
        if (!isset($this->client))
        {
            $this->initGuzzleClient();
        }

        $this->guzzle_headers['Referer'] = 'https://'.VAGRANT_DOMAIN.'/course/modify/'.$course->getId();

        $page='/course/'.$course->getId().'/unitspecs/new';
        $url = 'https://'.VAGRANT_DOMAIN.$page;
        $data =[
            'name' => $name
        ];

        $allow_redirects = $this->redirect_settings;

        try {
            return $this->client->request('POST', $url, [
                'headers' => $this->guzzle_headers,
                'allow_redirects' => $allow_redirects,
                'cookies'=>$this->client_cookie_jar,
                'form_params'=>$data
            ]);
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            print($e->getMessage());
            print($e->getResponse()->getBody()->getContents());
            throw $e;
        }
    }

    /**
     * saveCourseSpec fire off Curl to save course spec
     *
     * @param string $course_name
     * @param string $unit_name
     * @param int $parentId
     * @param \Schoolbox\Learning\Course\DataModel\Specification\Model $course_spec
     * @param \Schoolbox\Learning\Course\DataModel\Specification\Unit\Model $unit
     *
     * @throws GuzzleException
     *
     * @return array
     */
    public function saveCourseSpec($course_name, $unit_name, $parentId, $course_spec, $unit)
    {
        if (!isset($this->client))
        {
            $this->initGuzzleClient();
        }

        $this->guzzle_headers['Referer'] = 'https://'.VAGRANT_DOMAIN.'/course/modify/'.$course_spec->getId();

        $page='/course/modify/'.$course_spec->getId();
        $url = 'https://'.VAGRANT_DOMAIN.$page;
        $data =[
            'name' => $course_name,
            'parent' => $parentId,
            'curriculumCodes' => '',
            'unit['.$unit->getId().'][name]' => $unit_name,
            'newUnitName' => '',
            'submit' => 'Save Course',
        ];

        $allow_redirects = $this->redirect_settings;

        return $this->client->request('POST', $url, [
            'headers' => $this->guzzle_headers,
            'allow_redirects' => $allow_redirects,
            'cookies'=>$this->client_cookie_jar,
            'form_params'=>$data
        ]);
    }

    /**
     * createHomepage fire off Curl to create a homepage
     *
     * @param string $title
     *
     * @throws GuzzleException
     *
     * @return array
     */
    public function createHomepage($title)
    {
        return $this->createFolder($title, 9);
    }

    /**
     * createClass fire off Curl to create a class
     *
     * @param string $title
     * @param string $code
     *
     * @throws GuzzleException
     *
     * @return array
     */
    public function createClass($title, $code)
    {
        return $this->createFolder($title, 9, null, $code);
    }

    /**
     * createFolder fire off Curl to create a folder
     *
     * @param string $title
     * @param string $template
     * @param Schoolbox\Resource\Folder\Model $parant_folder
     * @param string $code
     *
     * @throws GuzzleException
     *
     * @return array
     */
    public function createFolder($title, $template=-1, $parent_folder=null, $code='')
    {

        if (!isset($this->client))
        {
            $this->initGuzzleClient();
        }

        $parent_id=0;

        if (isset($parent_folder))
        {
            $parent_id=$parent_folder->getId();
        }

        $this->guzzle_headers['Referer'] = 'https://'.VAGRANT_DOMAIN.'/resource/folder/create/'.$parent_id;

        $page='/resource/folder/update';
        $url = 'https://'.VAGRANT_DOMAIN.$page;

        $data = [   'parent'=>$parent_id,
            'categoryID'=>null,
            'name'=>$title,
            'template'=>$template,
            'colour'=>'',
            'group[1]'=>2,
            'group[2]'=>1,
            'group[3]'=>1,
            'group[4]'=>1,
            'group[5]'=>1,
            'group[6]'=>0,
            'code'=>$code,
            'curriculumCodes[]'=>''];

        if ($parent_id != 0)
        {
            $data['inherit']=1;
        }

        $allow_redirects = $this->redirect_settings;

        return $this->client->request('POST', $url, [
            'headers'=>$this->guzzle_headers,
            'form_params' => $data,
            'cookies' => $this->client_cookie_jar,
            'allow_redirects' => $allow_redirects
        ]);

    }

    /**
     * importCourseSpecToClass fire off Curl to import a course spec to a class
     *
     * @param int $course_spec_id
     * @param int $class_page_id
     *
     * @throws GuzzleException
     *
     * @return array
     */
    public function importCourseSpecToClass($course_spec_id, $class_page_id){
        if (!isset($this->client))
        {
            $this->initGuzzleClient();
        }

        $this->guzzle_headers['Referer'] = 'https://'.VAGRANT_DOMAIN.'/course/import/'.$course_spec_id.'/folder/0/0';

        $page='/course/import/classes';
        $url = 'https://'.VAGRANT_DOMAIN.$page;
        $data = [
            'course_spec_id' => $course_spec_id,
            'import_folders' => $class_page_id,
            'component_id' => '',
            'part-1' => 1
        ];

        $allow_redirects = $this->redirect_settings;

        return $this->client->request('POST', $url, [
            'headers' => $this->guzzle_headers,
            'allow_redirects' => $allow_redirects,
            'cookies'=>$this->client_cookie_jar,
            'form_params'=>$data
        ]);
    }

    /**
     * importUnitSpecToClassCourse fire off Curl to import all unit spec to a class based on course spec
     *
     * @param int $course_spec_id
     * @param int $class_page_id
     *
     * @throws GuzzleException
     *
     * @return array
     */
    public function importUnitSpecToClassCourse($course_spec_id, $class_page_id){
        if (!isset($this->client))
        {
            $this->initGuzzleClient();
        }

        $unitsForCourseSpec=$this->getUnitsByCourseSpecId($course_spec_id);

        $this->guzzle_headers['Referer'] = 'https://'.VAGRANT_DOMAIN.'/course/import/'.$course_spec_id.'/folder/0/0';

        $page='/course/import/units';
        $url = 'https://'.VAGRANT_DOMAIN.$page;
        $data = [
            'course_spec_id' => $course_spec_id,
            'import_folders' => $class_page_id,
            'component_id' => '',
            'part-2' => 1
        ];

        $count=1;
        foreach($unitsForCourseSpec as $unit)
        {
            $data['unit['.$count.'][unit_spec_id]']=$unit->getId();
            $data['unit['.$count.'][unit_impl_id]']='';
            $data['unit['.$count.'][import]']='on';
            $data['unit['.$count.'][open_date]']='22/06/2019';
            $data['unit['.$count.'][close_date]']='22/08/2019';
            $count++;
        }


        $allow_redirects = $this->redirect_settings;

        return $this->client->request('POST', $url, [
            'headers' => $this->guzzle_headers,
            'allow_redirects' => $allow_redirects,
            'cookies'=>$this->client_cookie_jar,
            'form_params'=>$data
        ]);
    }

    /**
     * importDueWorkToClassCourseUnit fire off Curl to import all due work to a class based on course unit spec
     *
     * @param int $course_spec_id
     * @param int $class_page_id
     *
     * @throws GuzzleException
     *
     * @return array
     */
    public function importDueWorkToClassCourseUnit($course_spec_id, $class_page_id){
        if (!isset($this->client))
        {
            $this->initGuzzleClient();
        }

        $unitsForCourseSpec=$this->getUnitsByCourseSpecId($course_spec_id);

        $this->guzzle_headers['Referer'] = 'https://'.VAGRANT_DOMAIN.'/course/import/'.$course_spec_id.'/folder/0/0';

        $page='/course/import/activities';
        $url = 'https://'.VAGRANT_DOMAIN.$page;
        $data = [
            'action' => 'import',
            'course_spec_id' => $course_spec_id,
            'import_folders' => $class_page_id,
            'component_id' => '',
            'part-3' => 1
        ];

        foreach($unitsForCourseSpec as $unit)
        {
            $current_unit_id=$unit->getId();
            $data['unit['.$current_unit_id.'][unit_spec_id]']=$current_unit_id;
            $data['unit['.$current_unit_id.'][unit_impl_id]']='';
            $data['unit['.$current_unit_id.'][open_date]']='22/06/2019';
            $data['unit['.$current_unit_id.'][close_date]']='22/08/2019';
            $assessments = $this->getAssessmentIdByCourseUnitSpecId($current_unit_id);
            foreach ($assessments as $assessment)
            {
                $data['unit['.$current_unit_id.'][a]['.$assessment['assessment_id'].'][spec_id]'] = $assessment['assessment_id'];
                $data['unit['.$current_unit_id.'][a]['.$assessment['assessment_id'].'][impl_id]'] = '';
                $data['unit['.$current_unit_id.'][a]['.$assessment['assessment_id'].'][checked]'] = 1;
                $data['unit['.$current_unit_id.'][a]['.$assessment['assessment_id'].'][dates][publish]'] ='22/06/2019 12:00am';
                $data['unit['.$current_unit_id.'][a]['.$assessment['assessment_id'].'][dates][due]'] ='22/08/2019 12:00am';
            }
        }

        $allow_redirects = $this->redirect_settings;

        return $this->client->request('POST', $url, [
            'headers' => $this->guzzle_headers,
            'allow_redirects' => $allow_redirects,
            'cookies'=>$this->client_cookie_jar,
            'form_params'=>$data
        ]);
    }

    /**
     * createPastoralCareRecord fire off Curl to create a pastoral care record for a given student
     *
     *
     * @param string $heading
     * @param int $student_id
     * @param array $public_viewers [$viewer_type => id] eg. ['role' => 1, 'user' => 124]
     * @param array $confidential_viewers [$viewer_type => id] eg. ['role' => 1, 'user' => 124]
     * @param array $blocked_viewers [$viewer_type => id] eg. ['role' => 1, 'user' => 124]
     *
     * @return array
     *@throws GuzzleException
     *
     */
    public function createPastoralCareRecord($heading, $student_id, $public_viewers, $confidential_viewers, $blocked_viewers)
    {

        if (!isset($this->client))
        {
            $this->initGuzzleClient();
        }

        $this->guzzle_headers['Referer'] = 'https://'.VAGRANT_DOMAIN.'/pastoral/student/'.$student_id.'/record/insert';

        $page='/pastoral/record/insert';
        $url = 'https://'.VAGRANT_DOMAIN.$page;
        $data=[
            'id' => '',
            'student_id'=>$student_id,
            'heading' => $heading,
            'typeId' => 1,
            'subtypeId' => 1,
            'severityId' => 1,
            'points' => 1,
            'tags' => '',
            'body_public' => '<p>automated public statement</p>',
            'body_private' => '<p>automated private statement</p>'
        ];

        foreach ($public_viewers as $type => $viewer_id)
        {
            $data[$type.'['.$viewer_id.']'] = 1;
        }

        foreach ($confidential_viewers as $type => $viewer_id)
        {
            $data[$type.'['.$viewer_id.']'] = 2;
        }

        foreach ($blocked_viewers as $type => $viewer_id)
        {
            $data[$type.'['.$viewer_id.']'] = 0;
        }

        $allow_redirects = $this->redirect_settings;

        return $this->client->request('POST', $url, [
            'headers' => $this->guzzle_headers,
            'allow_redirects' => $allow_redirects,
            'cookies'=>$this->client_cookie_jar,
            'form_params'=>$data
        ]);
    }

    /**
     * modifyPastoralCareRecord fire off Curl to modify an existing record
     *
     *
     * @param string $heading
     * @param string $pastoral_record_id
     * @param int $student_id
     * @param array $public_viewers [$viewer_type => id] eg. ['role' => 1, 'user' => 124]
     * @param array $confidential_viewers [$viewer_type => id] eg. ['role' => 1, 'user' => 124]
     * @param array $blocked_viewers [$viewer_type => id] eg. ['role' => 1, 'user' => 124]
     * @param int $duplicate_record_to ID of the student the record needs to be duplicated to.
     *
     * @return array
     *@throws GuzzleException
     *
     */
    public function modifyPastoralCareRecord($heading, $pastoral_record_id, $student_id, $public_viewers, $confidential_viewers, $blocked_viewers, $duplicate_record_to=null)
    {

        if (!isset($this->client))
        {
            $this->initGuzzleClient();
        }

        $this->guzzle_headers['Referer'] = 'https://'.VAGRANT_DOMAIN.'/pastoral/student/'.$student_id.'/record/'.$pastoral_record_id.'/update';

        $page='/pastoral/record/'.$pastoral_record_id.'/update';
        $url = 'https://'.VAGRANT_DOMAIN.$page;
        $data=[
            'id' => $pastoral_record_id,
            'student_id'=>$student_id,
            'heading' => $heading,
            'typeId' => 1,
            'subtypeId' => 1,
            'severityId' => 1,
            'points' => 1,
            'tags' => '',
            'body_public' => '<p>automated public statement</p>',
            'body_private' => '<p>automated private statement</p>'
        ];

        if ($duplicate_record_to)
        {
            $data['duplicate_id[]'] = $duplicate_record_to;
        }

        foreach ($public_viewers as $type => $viewer_id)
        {
            $data[$type.'['.$viewer_id.']'] = 1;
        }

        foreach ($confidential_viewers as $type => $viewer_id)
        {
            $data[$type.'['.$viewer_id.']'] = 2;
        }

        foreach ($blocked_viewers as $type => $viewer_id)
        {
            $data[$type.'['.$viewer_id.']'] = 0;
        }

        $allow_redirects = $this->redirect_settings;

        return $this->client->request('POST', $url, [
            'headers' => $this->guzzle_headers,
            'allow_redirects' => $allow_redirects,
            'cookies'=>$this->client_cookie_jar,
            'form_params'=>$data
        ]);
    }

    /**
     * createPastoralCareRecordComment fire off Curl to create a comment on an existing pastoral record
     *
     *
     * @param int $pastoral_record_id
     * @param int $student_id
     * @param string $comment
     * @param bool $confidential is this comment confidential?
     *
     * @throws GuzzleException
     *
     * @return array
     */
    public function createPastoralCareRecordComment($pastoral_record_id, $student_id, $comment, $confidential=false)
    {

        if (!isset($this->client))
        {
            $this->initGuzzleClient();
        }
        $this->guzzle_headers['Referer'] = 'https://'.VAGRANT_DOMAIN.'/pastoral/student/'.$student_id.'/record/'.$pastoral_record_id;

        $page='/pastoral/record/'.$pastoral_record_id.'/comment/insert';
        $url = 'https://'.VAGRANT_DOMAIN.$page;
        $data=[
            'pastoral_id' => $pastoral_record_id,
            'student_id'=>$student_id,
            'comment_body' => $comment,
        ];

        if ($confidential)
        {
            $data['comment_private']=1;
        }

        $allow_redirects = $this->redirect_settings;

        return $this->client->request('POST', $url, [
            'headers' => $this->guzzle_headers,
            'allow_redirects' => $allow_redirects,
            'cookies'=>$this->client_cookie_jar,
            'form_params'=>$data
        ]);
    }

    /**
     * createPersonalCalendarEventViaCalendar fire off Curl to create a personal calendar event.
     *
     *
     * @param string $event_title
     * @param int $user_id
     *
     * @throws GuzzleException
     *
     * @return array
     */
    public function createPersonalCalendarEventViaCalendar($event_title, $user_id)
    {
        if (!isset($this->client))
        {
            $this->initGuzzleClient();
        }

        $this->guzzle_headers['Referer'] = 'https://'.VAGRANT_DOMAIN.'/calendar/full';

        $page='/calendar/ajax/create';
        $url = 'https://'.VAGRANT_DOMAIN.$page;
        $data=[
            'userId' => $user_id,
            'allDay' => 1,
            'date' => '19-07-2019',
            'title' => $event_title,
            'type' => 1
        ];

        $allow_redirects = $this->redirect_settings;

        return $this->client->request('POST', $url, [
            'headers' => $this->guzzle_headers,
            'allow_redirects' => $allow_redirects,
            'cookies'=>$this->client_cookie_jar,
            'form_params'=>$data
        ]);
    }

    /**
     * modifyCalendarEventWithLevel fire off Curl to modify calendar event with data on what kind of event it is.
     *
     *
     * @param string $event_title
     * @param int $user_id
     * @param array $level_data contains extra data to append onto the form_params array
     * @param string $id supply an event ID if you are editing a pre existing event
     *
     * @throws GuzzleException
     *
     * @return array
     */
    public function modifyCalendarEventWithLevel($event_title, $user_id, $level_data, $id='')
    {
        if (!isset($this->client))
        {
            $this->initGuzzleClient();
        }

        $this->guzzle_headers['Referer'] = 'https://'.VAGRANT_DOMAIN.'/calendar/modify.php';

        $page='/calendar/modifyForm.php';
        $url = 'https://'.VAGRANT_DOMAIN.$page;
        $data=[
            'id' => $id,
            'userId' => $user_id,
            'authorId' => $user_id,
            'allday' => 1,
            'return' => '/calendar/full/19-07-2019/'.$user_id,
            'efficacy' => 'self',
            'action' => 'Add',
            'title' => $event_title,
            'startDate' => '19/07/2019',
            'endDate' => '19/07/2019',
            'repeat' => null,
            'repeat_until' => null,
            'type' => 1,
            'location' => 'Automated test location',
            'detail' => '<p>Automated test description</p>',
            'publishDate' => '18/07/2019 12:00pm'
        ];
        if ($id === '')
        {
            $data['Add'] ='Add Event';
        } else {
            $data['Add'] ='Update Event';
        }
        $data = array_merge($data, $level_data);

        $allow_redirects = $this->redirect_settings;

        try {
            return $this->client->request('POST', $url, [
                'headers' => $this->guzzle_headers,
                'allow_redirects' => $allow_redirects,
                'cookies'=>$this->client_cookie_jar,
                'form_params'=>$data
            ]);
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            print($e->getMessage());
            print($e->getResponse()->getBody()->getContents());
            throw $e;
        }
    }

    /**
     * createPersonalCalendarEvent fire off Curl to create a personal calendar event
     *
     *
     * @param string $event_title
     * @param int $user_id
     *
     * @throws GuzzleException
     *
     * @return array
     */
    public function createPersonalCalendarEvent($event_title, $user_id)
    {
        $level_data= [
            'level' => 1,
            'campus[]' => 1
        ];
        return $this->modifyCalendarEventWithLevel($event_title, $user_id, $level_data);
    }

    /**
     * createCampusCalendarEventWithAttendance fire off Curl to create a campus calendar event
     *
     *
     * @param string $event_title
     * @param int $user_id
     * @param array $roles
     * @param array $campuses
     *
     * @throws GuzzleException
     *
     * @return array
     */
    public function createCampusCalendarEventWithAttendance($event_title, $user_id, $roles, $campuses)
    {
        $level_data= [
            'level' => 3,
            'roles' => $roles,
            'campus' => $campuses,
            'attendance' => 1
        ];
        return $this->modifyCalendarEventWithLevel($event_title, $user_id, $level_data);
    }

    /**
     * createGroupCalendarEvent fire off Curl to create a group calendar event
     *
     *
     * @param string $event_title
     * @param int $user_id
     * @param array $event_groups
     *
     * @throws GuzzleException
     *
     * @return array
     */
    public function createGroupCalendarEvent($event_title, $user_id, $event_groups)
    {
        $level_data= [
            'level' => 2,
            'group' => $event_groups,
            'campus[]' => 1
        ];
        return $this->modifyCalendarEventWithLevel($event_title, $user_id, $level_data);
    }

    /**
     * createCampusCalendarEvent fire off Curl to create a group calendar event
     *
     *
     * @param string $event_title
     * @param int $user_id
     * @param array $roles
     * @param array $campuses
     *
     * @throws GuzzleException
     *
     * @return array
     */
    public function createCampusCalendarEvent($event_title, $user_id, $roles, $campuses)
    {
        $level_data= [
            'level' => 3,
            'roles' => $roles,
            'campus' => $campuses
        ];
        return $this->modifyCalendarEventWithLevel($event_title, $user_id, $level_data);
    }

    /**
     * editPersonalCalendarEvent fire off Curl to edit a personal calendar event
     *
     *
     * @param string $event_title
     * @param int $user_id
     *
     * @throws GuzzleException
     *
     * @return array
     */
    public function editPersonalCalendarEvent($event_title, $user_id)
    {
        $event = $this->getCalendarEventByTitle($event_title);

        $level_data= [
            'level' => 1,
            'campus' => [1]
        ];

        return $this->modifyCalendarEventWithLevel($event_title, $user_id, $level_data, $event->getId());
    }

    /**
     * RSVPtoEvent fire off Curl to respond with RSVP to an event
     *
     *
     * @param int $event_id
     *
     * @throws GuzzleException
     *
     * @return array
     */
    public function RSVPtoEvent($event_id)
    {
        if (!isset($this->client))
        {
            $this->initGuzzleClient();
        }

        $this->guzzle_headers['Referer'] = 'https://'.VAGRANT_DOMAIN.'/calendar/full/09-08-2019';

        $page='/service/attendance/setAttendance';
        $url = 'https://'.VAGRANT_DOMAIN.$page;
        $data=[
            'event[id]' => $event_id,
            'status' => 'add'
        ];

        $allow_redirects = $this->redirect_settings;

        return $this->client->request('POST', $url, [
            'headers' => $this->guzzle_headers,
            'allow_redirects' => $allow_redirects,
            'cookies'=>$this->client_cookie_jar,
            'form_params'=>$data
        ]);
    }

    /**
     * ApproveAttendeeOnRSVPEvent fire off Curl to approve all attendees of an event
     *
     *
     * @param int $event_id
     * @param int[] $user_ids
     *
     * @throws GuzzleException
     *
     * @return array
     */
    public function ApproveAttendeeOnRSVPEvent($event_id, $user_ids)
    {
        if (!isset($this->client))
        {
            $this->initGuzzleClient();
        }

        $this->guzzle_headers['Referer'] = 'https://'.VAGRANT_DOMAIN.'/calendar/attendance/list/'.$event_id;

        $attendanceList = $this->getCalendarEventAttendanceList($event_id);

        $page='/calendar/attendance/processModifyAttendanceList';
        $url = 'https://'.VAGRANT_DOMAIN.$page;
        $data=[
            'event[id]' => $event_id,
            'approve' => 'Approve Selected'
        ];

        foreach ($attendanceList as $attendee)
        {
            if ( in_array($attendee['user_id'], $user_ids, false))
            {
                $data['attendanceList['.$attendee['user_id'].'][id]'] = $attendee['id'];
            }
        }

        $allow_redirects = $this->redirect_settings;

        return $this->client->request('POST', $url, [
            'headers' => $this->guzzle_headers,
            'allow_redirects' => $allow_redirects,
            'cookies'=>$this->client_cookie_jar,
            'form_params'=>$data
        ]);
    }

    /**
     * addUsersToHomepageGroup fire off Curl to add users to a homepage group
     *
     *
     * @param int $homepage_id
     * @param int[] $user_ids
     *
     * @throws Exception
     * @throws GuzzleException
     *
     * @return array
     */
    public function addUsersToHomepageGroup($homepage_id, $user_ids)
    {
        if (!isset($this->client))
        {
            $this->initGuzzleClient();
        }

        $this->guzzle_headers['Referer'] = 'https://'.VAGRANT_DOMAIN.'/group/modify/'.$homepage_id;

        $page='/group/modifyForm.php';
        $url = 'https://'.VAGRANT_DOMAIN.$page;
        $data['categoryID']=$homepage_id;
        if (is_array($user_ids))
        {
            $data['userID']=$user_ids;
        }
        elseif (isset($user_ids))
        {
            $data['userID']=[$user_ids];
        } else {
            throw new Exception('Must have user ids to add to the group.');
        }

        $allow_redirects = $this->redirect_settings;

        return $this->client->request('POST', $url, [
            'headers' => $this->guzzle_headers,
            'allow_redirects' => $allow_redirects,
            'cookies'=>$this->client_cookie_jar,
            'form_params'=>$data
        ]);
    }

    /**
     * setHomepageGroupUserPermission fire off Curl to add user permissions to homepage group
     *
     *
     * @param int $homepage_id
     * @param array $user_id_permission_array ['user_id' => permission_level]
     *
     * @throws GuzzleException
     *
     * @return array
     */
    public function setHomepageGroupUserPermission($homepage_id, $user_id_permission_array)
    {
        if (!isset($this->client))
        {
            $this->initGuzzleClient();
        }

        $this->guzzle_headers['Referer'] = 'https://'.VAGRANT_DOMAIN.'/group/modify/'.$homepage_id;

        $page='/group/modifyForm.php';
        $url = 'https://'.VAGRANT_DOMAIN.$page;

        $data['categoryID']=$homepage_id;
        foreach ($user_id_permission_array as $key => $permission)
        {
            $data['accessLevel['.$key.']']=$permission;
        }

        $allow_redirects = $this->redirect_settings;

        return $this->client->request('POST', $url, [
            'headers' => $this->guzzle_headers,
            'allow_redirects' => $allow_redirects,
            'cookies'=>$this->client_cookie_jar,
            'form_params'=>$data
        ]);
    }

    /**
     * addDefaultUsersToHomepage use curls to add some default users to a homepage.
     *
     *
     * @param int $homepage_id
     *
     * @throws Exception
     * @throws GuzzleException
     */
    public function addDefaultUsersToHomepage($homepage_id)
    {
        if (!isset($this->client))
        {
            $this->initGuzzleClient();
        }

        $this->addUsersToHomepageGroup($homepage_id, [230, 151]);
        $this->setHomepageGroupUserPermission($homepage_id, ['151'=>3]);
    }

    /**
     * addComponentToHomepage fire off Curl to add a component to a homepage
     *
     *
     * @param int $element_id
     * @param int $homepage_id
     * @param int $location
     *
     * @throws GuzzleException
     *
     * @return array
     */
    public function addComponentToHomepage($element_id, $homepage_id, $location)
    {
        if (!isset($this->client))
        {
            $this->initGuzzleClient();
        }

        $this->guzzle_headers['Referer'] = 'https://'.VAGRANT_DOMAIN.'/homepage/'.$homepage_id;

        $page='/homepage/configForm.php';
        $url = 'https://'.VAGRANT_DOMAIN.$page;

        $data =[
            'add' => $element_id,
            'cid' => $homepage_id
        ];

        $allow_redirects = $this->redirect_settings;

        /** @var \GuzzleHttp\Psr7\Response $response */
        $response = $this->client->request('POST', $url, [
            'headers' => $this->guzzle_headers,
            'allow_redirects' => $allow_redirects,
            'cookies'=>$this->client_cookie_jar,
            'form_params'=>$data
        ]);

        $capture_new_component_id='/div id=\'component(\d+)\'/';
        preg_match($capture_new_component_id, $response->getBody(), $new_component_id_matches);

        $data['type']='components';
        $data['column'.$location]=$new_component_id_matches[1];
        $data['cid']=$homepage_id;

        $allow_redirects = $this->redirect_settings;

        /** @var \GuzzleHttp\Psr7\Response $response */
        return $this->client->request('POST', $url, [
            'headers' => $this->guzzle_headers,
            'allow_redirects' => $allow_redirects,
            'cookies'=>$this->client_cookie_jar,
            'form_params'=>$data
        ]);
    }

    /**
     * addComponentToHomepage fire off Curl to add text to a text box to the text component
     *
     *
     * @param int $homepage_id
     * @param $text_component
     * @param string $text
     * @param string $heading
     *
     * @throws GuzzleException
     *
     * @return array
     */
    public function addTextBoxToTextComponent($homepage_id, $text_component, $text, $heading)
    {

        if (!isset($this->client))
        {
            $this->initGuzzleClient();
        }

        $this->guzzle_headers['Referer'] = 'https://'.VAGRANT_DOMAIN.'/homepage/'.$homepage_id.'/textBoxCreate/'.$text_component->getId();

        $page='/textbox/textBoxForm.php';
        $url = 'https://'.VAGRANT_DOMAIN.$page;

        $data =[
            'originID'=>$homepage_id,
            'categoryID'=>$homepage_id,
            'textBoxID'=>'',
            'componentID'=>$text_component->getId(),
            'textBoxHeading'=>$heading,
            'textBoxBody'=>$text
        ];

        $allow_redirects = $this->redirect_settings;

        /** @var \GuzzleHttp\Psr7\Response $response */
        return $this->client->request('POST', $url, [
            'headers' => $this->guzzle_headers,
            'allow_redirects' => $allow_redirects,
            'cookies'=>$this->client_cookie_jar,
            'form_params' => $data
        ]);

    }

    /**
     * deleteTextBoxToTextComponent fire off Curl to delete text box from a text component based on heading
     *
     *
     * @param int $homepage_id
     * @param string $heading
     *
     * @throws GuzzleException
     *
     * @return array
     */
    public function deleteTextBoxToTextComponent($homepage_id, $heading)
    {
        if (!isset($this->client))
        {
            $this->initGuzzleClient();
        }

        $textBox = $this->getTextBoxByHeading($heading);

        $this->guzzle_headers['Referer'] = 'https://'.VAGRANT_DOMAIN.'/homepage/'.$homepage_id;

        $page='/textbox/textBoxForm.php';
        $url = 'https://'.VAGRANT_DOMAIN.$page;

        $query_string =[
            'delete' => $textBox->getId(),
            'originID' => $homepage_id
        ];

        $allow_redirects = $this->redirect_settings;

        /** @var \GuzzleHttp\Psr7\Response $response */
        return $this->client->request('GET', $url, [
            'headers' => $this->guzzle_headers,
            'allow_redirects' => $allow_redirects,
            'cookies'=>$this->client_cookie_jar,
            'query' => $query_string
        ]);
    }

    /**
     * createAssessemnt fire off Curl to create am assessment
     *
     *
     * @param string $contexttype
     * @param int $contextId
     * @param int $folderId
     * @param string $title
     * @param string $assessmentType
     *
     * @throws GuzzleException
     *
     * @return array
     */
    public function createAssessemnt($contexttype, $contextId, $folderId, $title, $assessmentType)
    {
        if (!isset($this->client))
        {
            $this->initGuzzleClient();
        }

        $this->guzzle_headers['Referer'] = 'https://'.VAGRANT_DOMAIN.'/learning/'.$contexttype.'/'.$contextId.'/assessments/new?type=='.$assessmentType;

        $page='/learning/'.$contexttype.'/'.$contextId.'/assessments';
        $url = 'https://'.VAGRANT_DOMAIN.$page;

        $allow_redirects = $this->redirect_settings;

        $data =[
            'contextType' => $contexttype,
            'contextId' => $contextId,
            'folderId' => $folderId,
            'assessmentType' => $assessmentType,
            'title' => $title,
            'description' => '<p>This is a test assignment for automated tests</p>',
            'acceptOnline' => 0,
            'publishDate' => '01/07/2019 12:00am',
            'openDate' => '01/07/2019 12:00am',
            'dueDate' => '10/07/2019 11:59pm',
            'closeDate' => '10/07/2019 11:59pm',
            'weight' => 14,
            'self_reviewable'=>'none',
            'visibility'=>2,
            'markType'=>1,
            'maxMark'=>'',
            'gradingScheme'=>1,
            'resultset'=>1,
            'formField'=>'rb',
            'rb[0][level][0][id]'=>'',
            'rb[0][level][0][name]'=>'',
            'rb[0][level][1][id]'=>'',
            'rb[0][level][1][name]'=>'',
            'curriculumCodes'=>'',
            'part-2'=>1
        ];

        if ($assessmentType === 'project')
        {
            $data['summator']='weighted';
        }

        try {
            return $this->client->request('POST', $url, [
                'headers' => $this->guzzle_headers,
                'allow_redirects' => $allow_redirects,
                'cookies'=>$this->client_cookie_jar,
                'form_params'=>$data
            ]);
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            print($e->getMessage());
            print($e->getResponse()->getBody()->getContents());
            throw $e;
        }
    }

    /**
     * createAssessmentOnHomepage fire off Curl to create am assessment on a homepage
     *
     *
     * @param string $title
     * @param int $HomePageID
     *
     * @throws GuzzleException
     *
     * @return array
     */
    public function createAssessmentOnHomepage($title, $HomePageID)
    {
        return $this->createAssessemnt('folders', $HomePageID, $HomePageID, $title, 'dueWork');
    }

    /**
     * createAssessmentOnCourseUnitSpec fire off Curl to create am assessment on a course spec
     *
     *
     * @param string $title
     * @param int $unit_id
     * @param int $unit_folder_id
     *
     * @throws GuzzleException
     *
     * @return array
     */
    public function createAssessmentOnCourseUnitSpec($title, $unit_id, $unit_folder_id)
    {
        return $this->createAssessemnt('unitspecs', $unit_id, $unit_folder_id, $title, 'dueWork');
    }

    /**
     * createAssessmentOnCourseUnitSpec fire off Curl to create am assessment project on a course spec
     *
     *
     * @param string $title
     * @param int $unit_id
     * @param int $unit_folder_id
     *
     * @throws GuzzleException
     *
     * @return array
     */
    public function createAssessmentProjectOnCourseUnitSpec($title, $unit_id, $unit_folder_id)
    {
        return $this->createAssessemnt('unitspecs', $unit_id, $unit_folder_id, $title, 'project');
    }

    /**
     * createAssessmentOnCourseUnitSpec fire off Curl to create am assessment on a project
     *
     *
     * @param string $title
     * @param int $project_id
     * @param int $unit_folder_id
     *
     * @throws GuzzleException
     *
     * @return array
     */
    public function createAssessmentOnProject($title, $project_id, $unit_folder_id)
    {
        return $this->createAssessemnt('projects', $project_id, $unit_folder_id, $title, 'dueWork');
    }

    /**
     * determineMarkInvalidation fire off Curl to determine if marks are invalidated
     *
     *
     * @param int $HomePageID
     * @param int $assignmentID
     * @param string $assignmentTitle
     *
     * @throws GuzzleException
     *
     * @return array
     */
    public function determineMarkInvalidation($HomePageID, $assignmentID, $assignmentTitle)
    {
        if (!isset($this->client))
        {
            $this->initGuzzleClient();
        }

        //https://vagrant.dev.schoolbox.cloud/rubric/determineMarkInvalidation
        $this->guzzle_headers['Referer'] = 'https://'.VAGRANT_DOMAIN.'/learning/assessment/'.$assignmentID.'/modify?returnUrl=';


        $page='/rubric/determineMarkInvalidation';
        $url = 'https://'.VAGRANT_DOMAIN.$page;

        $allow_redirects = $this->redirect_settings;

        /*
         *assessmentType=dueWork
            'returnUrl'=L2xlYXJuaW5nL2Fzc2Vzc21lbnRzLzM4MS8xOQ==     #L2xlYXJuaW5nL2Fzc2Vzc21lbnRzLzM4MS8xOQ== = /learning/assessments/381/19   /assignmentID/userID
        */

        $data =[
            'returnUrl'=>'',
            'id'=>$assignmentID,
            'folderId'=>$HomePageID,
            'title'=>$assignmentTitle,
            'workType'=>'',
            'description'=>'<p>This is a test assignment for automated tests</p>',
            'acceptOnline'=>0,
            'publishDate'=>'01/07/2019 12:00am',
            'openDate'=>'01/07/2019 12:00am',
            'dueDate'=>'02/07/2019 11:59pm',
            'closeDate'=>'02/07/2019 11:59pm',
            'weight'=>15,
            'self_reviewable'=>'none',
            'visibility'=>2,
            'assessmentType'=>['dueWork', 'dueWork'],
            'markType'=>1,
            'maxMark'=>'',
            'gradingScheme'=>1,
            'resultset'=>1,
            'formField'=>'rb',
            'rb['.$assignmentID.'][level][0][id]'=>'',
            'rb['.$assignmentID.'][level][0][name]'=>'',
            'rb['.$assignmentID.'][level][1][id]'=>'',
            'rb['.$assignmentID.'][level][1][name]'=>'',
            'participant-include[230]'=>1,
            'participant-plan[230]'=>0,
            'curriculumCodes[]'
        ];

        try {
            return $this->client->request('POST', $url, [
                'headers' => $this->guzzle_headers,
                'allow_redirects' => $allow_redirects,
                'cookies'=>$this->client_cookie_jar,
                'form_params'=>$data
            ]);
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            print($e->getMessage());
            print($e->getResponse()->getBody()->getContents());
            throw $e;
        }
    }

    /**
     * updateAssessmentOnHomepage fire off Curl to update an assessment on a homepage.
     *
     *
     * @param int $HomePageID
     * @param int $assignmentID
     * @param string $assignmentTitle
     *
     * @throws GuzzleException
     *
     * @return array
     */
    public function updateAssessmentOnHomepage($HomePageID, $assignmentID, $assignmentTitle)
    {
        if (!isset($this->client))
        {
            $this->initGuzzleClient();
        }

        //https://vagrant.dev.schoolbox.cloud/learning/assessments/381
        $this->guzzle_headers['Referer'] = 'https://'.VAGRANT_DOMAIN.'/learning/assessment/'.$assignmentID.'/modify?returnUrl=';

        $page='/learning/assessments/'.$assignmentID;
        $url = 'https://'.VAGRANT_DOMAIN.$page;

        $allow_redirects = $this->redirect_settings;

        /*
         *
         * assessmentType=dueWork
            returnUrl=L2xlYXJuaW5nL2Fzc2Vzc21lbnRzLzM4MS8xOQ== #L2xlYXJuaW5nL2Fzc2Vzc21lbnRzLzM4MS8xOQ== = /learning/assessments/381/19      /assignmentID/userID
         */
        $data =[
            'returnUrl'=>'',
            'id'=>$assignmentID,
            'folderId'=>$HomePageID,
            'title'=>$assignmentTitle,
            'workType'=>'',
            'description'=>'<p>This is a test assignment for automated tests</p>',
            'acceptOnline'=>0,
            'publishDate'=>'01/07/2019 12:00am',
            'openDate'=>'01/07/2019 12:00am',
            'dueDate'=>'02/07/2019 11:59pm',
            'closeDate'=>'02/07/2019 11:59pm',
            'weight'=>15,
            'self_reviewable'=>'none',
            'visibility'=>2,
            'assessmentType'=>['dueWork', 'dueWork'],
            'markType'=>1,
            'maxMark'=>'',
            'gradingScheme'=>1,
            'resultset'=>1,
            'formField'=>'rb',
            'rb['.$assignmentID.'][level][0][id]'=>'',
            'rb['.$assignmentID.'][level][0][name]'=>'',
            'rb['.$assignmentID.'][level][1][id]'=>'',
            'rb['.$assignmentID.'][level][1][name]'=>'',
            'participant-include[230]'=>1,
            'participant-plan[230]'=>0,
            'curriculumCodes[]',
            'part-2'=>1
        ];

        try {
            return $this->client->request('POST', $url, [
                'headers' => $this->guzzle_headers,
                'allow_redirects' => $allow_redirects,
                'cookies'=>$this->client_cookie_jar,
                'form_params'=>$data
            ]);
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            print($e->getMessage());
            print($e->getResponse()->getBody()->getContents());
            throw $e;
        }
    }

    /**
     * createAssessementForHomepageWithRubric fire off Curl to create am assessment with rubric marks set
     *
     *
     * @param string $title
     * @param int $HomePageID
     *
     * @throws GuzzleException
     *
     * @return array
     */
    public function createAssessementForHomepageWithRubric($title, $HomePageID)
    {

        if (!isset($this->client))
        {
            $this->initGuzzleClient();
        }

        $this->guzzle_headers['Referer'] = 'https://'.VAGRANT_DOMAIN.'/learning/folders/'.$HomePageID.'/new?returnUrl=';

        $page='/rubric';
        $url = 'https://'.VAGRANT_DOMAIN.$page;

        $allow_redirects=false;

        $capture_criterion_group_id='/rb\[0\]\[criterion\]\[(\d+)]\[groupId\]" value="(\d+)/';
        #So that we can loop through getting the ID's for components create a skeleton to allow us to get it done. Need to update all the - values with real values.
        $rubySkeleton[0] = ['group' => [['groupId' => -1],
            ['groupId' => -2],
            ['groupId' => -3]],
            'criterion' => ['-1' => [ 'id' => -1,
                'groupId'=> -1],
                '-2' => [ 'id' => -2,
                    'groupId'=> -1],
                '-3' => [ 'id' => -3,
                    'groupId'=> -1]
            ]
        ];

        $copySkeletonForIteration = $rubySkeleton;
        $counter=0;
        foreach ($copySkeletonForIteration[0]['group'] as $group)
        {
            $data1 =[
                'type'=>'group',
                'rubricId'=>0,
                'groupId'=>'',
                'groupIndex'=>$counter,
                'name'=>'I'.$counter,
                'levelCount'=>3,
                'folderId'=>$HomePageID,
                'markPerCapability'=>true
            ];

            try {
                    $this->client->request('POST', $url, [
                    'headers' => $this->guzzle_headers,
                    'allow_redirects' => $allow_redirects,
                    'cookies'=>$this->client_cookie_jar,
                    'form_params'=>$data1
                ]);
            } catch (\GuzzleHttp\Exception\ClientException $e) {
                print($e->getMessage());
                print($e->getResponse()->getBody()->getContents());
                throw $e;
            }

            $counterCriterion=0;

            ##This is a pain but I need to walk through so that I hit all the end point posts for each criterion and group id for rubrics
            foreach ($copySkeletonForIteration[0]['criterion'] as $criterion)
            {
                $data2 =[
                    'type'=>'criterion',
                    'rubricId'=>0,
                    'groupId'=>'',
                    'groupIndex'=>0,
                    'name'=>'a'.$counterCriterion,
                    'levelCount'=>3,
                    'folderId'=>$HomePageID,
                    'markPerCapability'=>true,
                    'groupName'=>$data1['name']
                ];

                try {
                    $response = $this->client->request('POST', $url, [
                        'headers' => $this->guzzle_headers,
                        'allow_redirects' => $allow_redirects,
                        'cookies'=>$this->client_cookie_jar,
                        'form_params'=>$data2
                    ]);
                } catch (\GuzzleHttp\Exception\ClientException $e) {
                    print($e->getMessage());
                    print($e->getResponse()->getBody()->getContents());
                    throw $e;
                }
                $criterion_group_id_matches=[];
                preg_match($capture_criterion_group_id, $response->getBody(),$criterion_group_id_matches);
                $rubySkeleton[0]['group'][$counter]=['id' => $criterion_group_id_matches[2]];
                $rubySkeleton[0]['criterion'][$criterion_group_id_matches[1]]['groupId']=$criterion_group_id_matches[2];
                $rubySkeleton[0]['criterion'][$criterion_group_id_matches[1]]['id']=$criterion_group_id_matches[1];
                $rubySkeleton[0]['criterion'][$criterion_group_id_matches[1]]['on']=1;
                $rubySkeleton[0]['criterion'][$criterion_group_id_matches[1]]['name']=''.$counterCriterion;

                $rubySkeleton[0]['descriptor'][$criterion_group_id_matches[1]][0]['id']='';
                $rubySkeleton[0]['descriptor'][$criterion_group_id_matches[1]][1]['id']='';
                $rubySkeleton[0]['descriptor'][$criterion_group_id_matches[1]][2]['id']='';
                $rubySkeleton[0]['descriptor'][$criterion_group_id_matches[1]][0]['text']='A'.$counterCriterion;
                $rubySkeleton[0]['descriptor'][$criterion_group_id_matches[1]][1]['text']='B'.$counterCriterion;
                $rubySkeleton[0]['descriptor'][$criterion_group_id_matches[1]][2]['text']='C'.$counterCriterion;
                if($counterCriterion===2){
                    $rubySkeleton[0]['descriptor'][$criterion_group_id_matches[1]][0]['points']='';
                    $rubySkeleton[0]['descriptor'][$criterion_group_id_matches[1]][1]['points']='';
                    $rubySkeleton[0]['descriptor'][$criterion_group_id_matches[1]][2]['points']='';
                } else {
                    $rubySkeleton[0]['descriptor'][$criterion_group_id_matches[1]][0]['points']=1;
                    $rubySkeleton[0]['descriptor'][$criterion_group_id_matches[1]][1]['points']=2;
                    $rubySkeleton[0]['descriptor'][$criterion_group_id_matches[1]][2]['points']=3;
                }
                unset($rubySkeleton[0]['criterion'][$criterion['id']]);

                $counterCriterion++;
            }

            $counter++;
        }

        $rubySkeleton[0]['level'][0]['id']='';
        $rubySkeleton[0]['level'][0]['name']='A';
        $rubySkeleton[0]['level'][1]['id']='';
        $rubySkeleton[0]['level'][1]['name']='B';
        $rubySkeleton[0]['level'][2]['id']='';
        $rubySkeleton[0]['level'][2]['name']='C';
        $rubySkeleton[0]['group'][0]['name']='I';
        $rubySkeleton[0]['group'][1]['name']='II';
        $rubySkeleton[0]['group'][2]['name']='III';


        $data= [ 'contextType'=>'folders',
            'contextId' => $HomePageID,
            'folderId' => $HomePageID,
            'assessmentType' => 'dueWork',
            'title' => $title,
            'description' => '<p>This is a test assignment for automated tests</p>',
            'acceptOnline' => 0,
            'publishDate' => '01/07/2019 12:00am',
            'openDate' => '01/07/2019 12:00am',
            'dueDate' => '10/07/2019 11:59pm',
            'closeDate' => '10/07/2019 11:59pm',
            'weight' => 13,
            'self_reviewable'=>'none',
            'visibility'=>2,
            'markType'=>1,
            'maxMark'=>9,
            'gradingScheme'=>1,
            'resultset'=>1,
            'useRubric'=>'on',
            'formField'=>'rb',
            'rb'=>$rubySkeleton,
            'curriculumCodes'=>[],
            'participant-include[230]'=>1,
            'participant-plan[230]'=>0,
            'part-2'=>1
        ];

        $this->guzzle_headers['Referer'] = 'https://'.VAGRANT_DOMAIN.'/learning/folders/'.$HomePageID.'/assessments/new?type=dueWork';

        $page='/learning/folders/'.$HomePageID.'/assessments';
        $url = 'https://'.VAGRANT_DOMAIN.$page;

        $allow_redirects=$this->redirect_settings;

        try {
            return $this->client->request('POST', $url, [
                'headers' => $this->guzzle_headers,
                'allow_redirects' => $allow_redirects,
                'cookies'=>$this->client_cookie_jar,
                'form_params'=>$data
            ]);
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            print($e->getMessage());
            print($e->getResponse()->getBody()->getContents());
            throw $e;
        }

    }

    /**
     * submitAssessment fire off Curl to submit an assessment
     *
     *
     * @param int $assessmentID
     * @param int $user_id
     *
     * @return array
     *@throws GuzzleException
     *
     */
    public function submitAssessment($assessmentID, $user_id): ?array
    {
        if (!isset($this->client))
        {
            $this->initGuzzleClient();
        }

        $this->guzzle_headers['Referer'] = 'https://'.VAGRANT_DOMAIN.'/learning/assessments/'.$assessmentID .'/'.$user_id.'/submit';

        $page='/learning/assessments/'.$assessmentID .'/'.$user_id.'/submit';
        $url = 'https://'.VAGRANT_DOMAIN.$page;

        $allow_redirects = $this->redirect_settings;

        $data = [
            'responseId'=>'',
            'submitted_in_person' => 'on'
        ];

        try {
            return $this->client->request('POST', $url, [
                'headers' => $this->guzzle_headers,
                'allow_redirects' => $allow_redirects,
                'cookies'=>$this->client_cookie_jar,
                'form_params'=>$data
            ]);
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            print($e->getMessage());
            print($e->getResponse()->getBody()->getContents());
            throw $e;
        }
    }

    /**
     * markAssessment fire off Curl to submit an assessment
     *
     *
     * @param int $assessmentID
     * @param int $user_marking_id
     * @param int $student_submitting_id
     *
     * @return array
     *@throws GuzzleException
     *
     */
    public function markAssessment($assessmentID, $user_marking_id, $student_submitting_id)
    {
        if (!isset($this->client))
        {
            $this->initGuzzleClient();
        }

        $submissionID = $this->getFirstSubmissionIdByOwnerAndAssessmentID($assessmentID, $user_marking_id, $student_submitting_id);

        $this->guzzle_headers['Referer'] = 'https://'.VAGRANT_DOMAIN.'/learning/assessments/'.$assessmentID .'/'.$student_submitting_id.'/responses';

        $page='/learning/assessments/'.$assessmentID .'/'.$student_submitting_id.'/'.$submissionID.'/feedback';
        $url = 'https://'.VAGRANT_DOMAIN.$page;

        $allow_redirects = $this->redirect_settings;

        $data = [
            'responseId'=>$submissionID,
            'fromInlineGrader' => true,
            'mark' => '55',
            'comment' => 'Some Feedback for Student',
            'publish' => 1
        ];

        try {
            return $this->client->request('POST', $url, [
                'headers' => $this->guzzle_headers,
                'allow_redirects' => $allow_redirects,
                'cookies'=>$this->client_cookie_jar,
                'form_params'=>$data
            ]);
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            print($e->getMessage());
            print($e->getResponse()->getBody()->getContents());
            throw $e;
        }
    }

    /**
     * markAssessmentWithRubric fire off Curl to submit an assessment with a rubric
     *
     *
     * @param int $assessmentID
     * @param int $user_marking_id
     * @param int $student_submitting_id
     *
     * @return array
     *
     * @throws GuzzleException
     * @throws \Exception
     *
     */
    public function markAssessmentWithRubric($assessmentID, $user_marking_id, $student_submitting_id)
    {
        if (!isset($this->client))
        {
            $this->initGuzzleClient();
        }

        $submissionID = $this->getFirstSubmissionIdByOwnerAndAssessmentID($assessmentID, $user_marking_id, $student_submitting_id);
        /** @var Schoolbox\Assessment\Rubric\Grid\Grid $markGrid */
        $markGrid = $this->getAssessmentRubricGrid($assessmentID); ## Has been deprecated needs a recode.

        $this->guzzle_headers['Referer'] = 'https://'.VAGRANT_DOMAIN.'/learning/assessments/'.$assessmentID.'/'.$student_submitting_id.'/responses';

        $page='/learning/assessments/'.$assessmentID.'/'.$student_submitting_id.'/'.$submissionID.'/feedback';
        $url = 'https://'.VAGRANT_DOMAIN.$page;

        $allow_redirects = $this->redirect_settings;

        $rubric_data=[];

        foreach ($markGrid->getCapabilityUsages() as $group_capability) {
            foreach ($group_capability->getIndicatorUsages() as $capability_indicator)
            {
                $descriptors = $capability_indicator->getDescriptors();
                $levelToPick=random_int(0,2);
                $rubric_data['descriptors'][$descriptors[$levelToPick]->getId()]['selected']= 1;
            }
        }

        $data = [
            'responseId'=>$submissionID,
            'fromInlineGrader' => true,
            'mark' => '55',
            'comment' => 'Some Feedback for Student',
            'rubric' => $rubric_data,
            'publish' => 1
        ];

        try {
            return $this->client->request('POST', $url, [
                'headers' => $this->guzzle_headers,
                'allow_redirects' => $allow_redirects,
                'cookies'=>$this->client_cookie_jar,
                'form_params'=>$data
            ]);
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            print($e->getMessage());
            print($e->getResponse()->getBody()->getContents());
            throw $e;
        }
    }

    /**
     * draftNews fire off Curl to create a draft news article
     *
     *
     * @param string $title
     *
     * @throws GuzzleException
     *
     * @return array
     */
    public function draftNews($title)
    {
        if (!isset($this->client))
        {
            $this->initGuzzleClient();
        }

        $this->guzzle_headers['Referer'] = 'https://'.VAGRANT_DOMAIN.'/news/create';

        $page='/news/create';
        $url = 'https://'.VAGRANT_DOMAIN.$page;

        $allow_redirects = $this->redirect_settings;

        $data =[
            'returnUrl' => Querystring::encode('/'),
            'title' => $title,
            'body' => '',
            'blurb' => '',
            'audience' => 'role:IjMi:self',
            'publishAt' => '01/07/2019 12:47pm',
            'expiresAt' => '08/07/2019 11:59pm',
            'draft'=> true
        ];

        try {
            return $this->client->request('POST', $url, [
                'headers' => $this->guzzle_headers,
                'allow_redirects' => $allow_redirects,
                'cookies'=>$this->client_cookie_jar,
                'form_params'=>$data
            ]);
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            print($e->getMessage());
            print($e->getResponse()->getBody()->getContents());
            throw $e;
        }
    }

    /**
     * createNewsToModerate fire off Curl to create a moderated news article
     *
     *
     * @param string $title
     * @param int $news_id
     *
     * @throws GuzzleException
     *
     * @return array
     */
    public function createNewsToModerate($title, $news_id)
    {
        if (!isset($this->client))
        {
            $this->initGuzzleClient();
        }

        $this->guzzle_headers['Referer'] = 'https://'.VAGRANT_DOMAIN.'/news/'.$news_id.'/update';

        $page='/news/moderation/'.$news_id.'/submit';
        $url = 'https://'.VAGRANT_DOMAIN.$page;

        $allow_redirects = $this->redirect_settings;

        $data =[
            'returnUrl' => Querystring::encode('/'),
            'title' => $title,
            'body' => '',
            'blurb' => '',
            'audience' => 'role:IjMi:self',
            'publishAt' => '01/07/2019 12:47pm',
            'expiresAt' => '08/07/2019 11:59pm'
        ];

        try {
            return $this->client->request('POST', $url, [
                'headers' => $this->guzzle_headers,
                'allow_redirects' => $allow_redirects,
                'cookies'=>$this->client_cookie_jar,
                'form_params'=>$data
            ]);
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            print($e->getMessage());
            print($e->getResponse()->getBody()->getContents());
            throw $e;
        }
    }

    /**
     * publishModeratedNewsByID fire off Curl to publish a news article that is right now being moderated
     *
     *
     * @param int $news_id
     *
     * @throws GuzzleException
     *
     * @return array
     */
    public function publishModeratedNewsByID($news_id)
    {
        if (!isset($this->client))
        {
            $this->initGuzzleClient();
        }

        $this->guzzle_headers['Referer'] = 'https://'.VAGRANT_DOMAIN.'/news/'.$news_id.'/update';

        $page='/news/moderation/'.$news_id.'/accept';
        $url = 'https://'.VAGRANT_DOMAIN.$page;

        $allow_redirects = $this->redirect_settings;

        $data =[
            'articleID' => $news_id
        ];

        try {
            return $this->client->request('POST', $url, [
                'headers' => $this->guzzle_headers,
                'allow_redirects' => $allow_redirects,
                'cookies'=>$this->client_cookie_jar,
                'form_params'=>$data
            ]);
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            print($e->getMessage());
            print($e->getResponse()->getBody()->getContents());
            throw $e;
        }
    }

    /**
     * saveNewsByIDforLater fire off Curl to save a news article to read later.
     *
     *
     * @param int $news_id
     *
     * @throws GuzzleException
     *
     * @return array
     */
    public function saveNewsByIDforLater($news_id)
    {
        if (!isset($this->client))
        {
            $this->initGuzzleClient();
        }

        $this->guzzle_headers['Referer'] = 'https://'.VAGRANT_DOMAIN.'/news';

        $page='/news/saved/'.$news_id.'/add';
        $url = 'https://'.VAGRANT_DOMAIN.$page;

        $allow_redirects = $this->redirect_settings;

        $data =[];

        try {
            return $this->client->request('POST', $url, [
                'headers' => $this->guzzle_headers,
                'allow_redirects' => $allow_redirects,
                'cookies'=>$this->client_cookie_jar,
                'form_params'=>$data
            ]);
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            print($e->getMessage());
            print($e->getResponse()->getBody()->getContents());
            throw $e;
        }
    }

    /**
     * rejectNewsByID fire off Curl to reject a news article being moderated
     *
     *
     * @param int $news_id
     *
     * @throws GuzzleException
     *
     * @return array
     */
    public function rejectNewsByID($news_id)
    {
        if (!isset($this->client))
        {
            $this->initGuzzleClient();
        }

        $this->guzzle_headers['Referer'] = 'https://'.VAGRANT_DOMAIN.'/news/'.$news_id.'/update';

        $page='/news/moderation/'.$news_id.'/reject';
        $url = 'https://'.VAGRANT_DOMAIN.$page;

        $allow_redirects = $this->redirect_settings;

        $data =[
            'articleID' => $news_id,
            'rejectReason' => 'The reason is valid'
        ];

        try {
            return $this->client->request('POST', $url, [
                'headers' => $this->guzzle_headers,
                'allow_redirects' => $allow_redirects,
                'cookies'=>$this->client_cookie_jar,
                'form_params'=>$data
            ]);
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            print($e->getMessage());
            print($e->getResponse()->getBody()->getContents());
            throw $e;
        }
    }

    /**
     * deleteNewsByID fire off Curl to delete a news article.
     *
     *
     * @param int $news_id
     *
     * @throws GuzzleException
     *
     * @return array
     */
    public function deleteNewsByID($news_id)
    {

        if (!isset($this->client))
        {
            $this->initGuzzleClient();
        }

        $this->guzzle_headers['Referer'] = 'https://'.VAGRANT_DOMAIN.'/news/'.$news_id.'/update';

        $page='/news/'.$news_id.'/delete';
        $url = 'https://'.VAGRANT_DOMAIN.$page;

        $allow_redirects = $this->redirect_settings;

        $data =[
            'articleID' => $news_id
        ];

        try {
            return $this->client->request('POST', $url, [
                'headers' => $this->guzzle_headers,
                'allow_redirects' => $allow_redirects,
                'cookies'=>$this->client_cookie_jar,
                'form_params'=>$data
            ]);
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            print($e->getMessage());
            print($e->getResponse()->getBody()->getContents());
            throw $e;
        }
    }

    /**
     * publishNewsForm fire off Curl to publish a draft
     *
     *
     * @param string $title
     * @param int $news_id
     *
     * @throws GuzzleException
     *
     * @return array
     */
    public function publishNewsForm($title, $news_id)
    {

        if (!isset($this->client))
        {
            $this->initGuzzleClient();
        }

        $this->guzzle_headers['Referer'] = 'https://'.VAGRANT_DOMAIN.'/news/'.$news_id.'/update';

        $page='/news/moderation/'.$news_id.'/accept';
        $url = 'https://'.VAGRANT_DOMAIN.$page;

        $allow_redirects = $this->redirect_settings;

        $data =[
            'title' => $title,
            'body' => '',
            'blurb' => '',
            'audience' => 'role:IjMi:self',
            'publishAt' => '01/07/2019 12:47pm',
            'expiresAt' => '08/07/2019 11:59pm'
        ];

        try {
            return $this->client->request('POST', $url, [
                'headers' => $this->guzzle_headers,
                'allow_redirects' => $allow_redirects,
                'cookies'=>$this->client_cookie_jar,
                'form_params'=>$data
            ]);
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            print($e->getMessage());
            print($e->getResponse()->getBody()->getContents());
            throw $e;
        }
    }

    /**
     * loginAdmin uses curl to log guzzle client into schoolbox using admin credentials
     *
     * @throws Exception
     * @throws GuzzleException
     *
     */
    public function loginAdmin()
    {
        $admin_username='alaress';
        $admin_password='alaress';
        /** @var Response $response */
        $response = $this->loginUser($admin_username, $admin_password, false);
        if ($response->getStatusCode() == 303)
        {
            return true;
        }
        throw new Exception('Unable to login for some reason');
    }

}