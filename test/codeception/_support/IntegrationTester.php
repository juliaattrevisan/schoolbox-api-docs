<?php
/**
 * @author    Alexamder Tsuru <alexander.simonov@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

use Alaress\Redis;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use Schoolbox\Comms\News\Querystring;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Realtime\MessageList\ListFactory;
use Schoolbox\Realtime\Notification\Decorator;
use Schoolbox\UserMapper;
use Schoolbox\Comms\News\Mapper as NewsMapper;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Learning\Assessment\DataModel\Mapper as AssessmentMapper;
use Schoolbox\Assessment\Component\SubmissionBox\Rubric\RubricMapper;
use Schoolbox\Assessment\Component\SubmissionBox\Submission\SubmissionMapper;
use Schoolbox\Assessment\Component\SubmissionBoxMapper;
use Schoolbox\Application\CliTool\Kernel;
use Schoolbox\Application\CliTool\App;

/**
 * Inherited Methods
 * @method void wantToTest($text)
 * @method void wantTo($text)
 * @method void execute($callable)
 * @method void expectTo($prediction)
 * @method void expect($prediction)
 * @method void amGoingTo($argumentation)
 * @method void am($role)
 * @method void lookForwardTo($achieveValue)
 * @method void comment($description)
 * @method \Codeception\Lib\Friend haveFriend($name, $actorClass = null)
 *
 * @SuppressWarnings(PHPMD)
 */
class IntegrationTester extends \Codeception\Actor
{
    use _generated\IntegrationTesterActions;

    /**
     * Define custom actions here
     */
    const VAGRANT_DOMAIN = 'vagrant.dev.schoolbox.cloud';

    public $client;
    public $cli_kernel;
    public $client_cookie_jar;
    public $guzzle_headers=[];
    public $redirect_settings;
    public $database;

    function initGuzzleClient()
    {
        $this->client = new Client(['base_uri' => 'https://'.$this::VAGRANT_DOMAIN, 'timeout' => 5.0, 'cookies' => true]);
        $this->guzzle_headers=[ 'Host' => $this::VAGRANT_DOMAIN,
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

    function initDatabase()
    {
        $this->database=Database::getInstance();
        $this->database->connect('mysql:host=localhost;dbname=schoolbox', 'root', 'tnp879');
    }

    function initCliKernel()
    {

        $app_class = new class () extends App {
            public function run() : void{}
        };

        $this->cli_kernel = new Kernel($app_class, false);
    }

    function getUser($username){

        if(!isset($this->database)) {
            $this->initDatabase();
        }
        return UserMapper::getInstance()->findOneByColumn('username', $username);
    }

    function getNewsArticle($title, $latest_id=true){

        if(!isset($this->database)) {
            $this->initDatabase();
        }
        return NewsMapper::getInstance()->findOneByColumn('title', $title);
    }

    ##might not even work.
    function getFirstSubmissionIdByOwnerAndAssessmentID($assessmentID, $assessment_ownderID, $submission_ownderID)
    {
        if(!isset($this->database)) {
            $this->initDatabase();
        }
        $box_id=SubmissionBoxMapper::getInstance()->findOneByColumnList(['assessment_id'=>$assessmentID, 'owner'=>$assessment_ownderID])->getID();
        $submission = SubmissionMapper::getInstance()->findOneByColumnList(['box_id'=>$box_id, 'owner'=>$submission_ownderID]);
        return $submission->getId();
    }

    function getFirstHomepageIdByTitle($title)
    {
        if(!isset($this->database)) {
            $this->initDatabase();
        }
        /** @var Schoolbox\Resource\Folder\Model $folder */
        $folder = FolderMapper::getInstance()->findOneByColumn('name', $title);
        return $folder->getId();
    }

    function getFirstAssessmentIdByTitle($title)
    {
        if(!isset($this->database)) {
            $this->initDatabase();
        }
        /** @var Schoolbox\Learning\Assessment\DataModel\Mapper $DueWork */
        $assessment = AssessmentMapper::getInstance()->findOneByColumn('title', $title);
        return $assessment->getId();
    }

    function getAssessmentRubricGrid($assessmentID)
    {
        if(!isset($this->database)) {
            $this->initDatabase();
        }
        if(!isset($this->cli_kernel)) {
            $this->initCliKernel();
        }

        /** @var IAssessable $assessment */
        $assessment = $this->cli_kernel->get('assessmentDataSource')->findById($assessmentID);

        return RubricMapper::getInstance()->retrieveGrid($assessment, true);
    }

    function waitForUserMessage($user, $time_created, $timeout=10, $filter){
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

    public function loginUser($username, $password, $redirect=true)
    {
        if (!isset($this->client))
        {
            $this->initGuzzleClient();
        }

        $this->guzzle_headers['Referer'] = 'https://'.$this::VAGRANT_DOMAIN.'/login/';

        $page='/login/';
        $url = 'https://'.$this::VAGRANT_DOMAIN.$page;
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

    public function createHomepage($title)
    {

        if (!isset($this->client))
        {
            $this->initGuzzleClient();
        }


        $this->guzzle_headers['Referer'] = 'https://'.$this::VAGRANT_DOMAIN.'/resource/folder/create/0';

        $page='/resource/folder/update';
        $url = 'https://'.$this::VAGRANT_DOMAIN.$page;

        $data = [   'parent'=>0,
                    'categoryID'=>null,
                    'name'=>$title,
                    'template'=>9,
                    'colour'=>'',
                    'group[1]'=>2,
                    'group[2]'=>1,
                    'group[3]'=>1,
                    'group[4]'=>1,
                    'group[5]'=>1,
                    'group[6]'=>0,
                    'code'=>'',
                    'curriculumCodes[]'=>''];

        $allow_redirects = $this->redirect_settings;

        return $this->client->request('POST', $url, [
            'headers'=>$this->guzzle_headers,
            'form_params' => $data,
            'cookies' => $this->client_cookie_jar,
            'allow_redirects' => $allow_redirects
        ]);

    }

    public function addDefaultUsersToHomepage($homepage_id)
    {
        if (!isset($this->client))
        {
            $this->initGuzzleClient();
        }

        $this->guzzle_headers['Referer'] = 'https://'.$this::VAGRANT_DOMAIN.'/group/modify/'.$homepage_id;

        $page='/group/modifyForm.php';
        $url = 'https://'.$this::VAGRANT_DOMAIN.$page;
        $data=[ 'categoryID'=>$homepage_id,
                'userID'=> [ 230, 151] ];

        $allow_redirects = false;

        $this->client->request('POST', $url, [
            'headers' => $this->guzzle_headers,
            'allow_redirects' => $allow_redirects,
            'cookies'=>$this->client_cookie_jar,
            'form_params'=>$data
        ]);


        $data=[ 'categoryID'=>$homepage_id,
                'accessLevel[151]'=>3];

        $allow_redirects = false;

        $this->client->request('POST', $url, [
            'headers' => $this->guzzle_headers,
            'allow_redirects' => $allow_redirects,
            'cookies'=>$this->client_cookie_jar,
            'form_params'=>$data
        ]);


    }

    public function createAssessemntOnHomepage($title, $HomePageID)
    {
        if (!isset($this->client))
        {
            $this->initGuzzleClient();
        }

        $this->guzzle_headers['Referer'] = 'https://'.$this::VAGRANT_DOMAIN.'/learning/folders/'.$HomePageID.'/assessments/new?type=dueWork';

        $page='/learning/folders/'.$HomePageID.'/assessments';
        $url = 'https://'.$this::VAGRANT_DOMAIN.$page;

        $allow_redirects = $this->redirect_settings;

        $data =[
            'contextType' => 'folders',
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

    public function determineMarkInvalidation($HomePageID, $assignmentID, $assignmentTitle)
    {
        if (!isset($this->client))
        {
            $this->initGuzzleClient();
        }

        //https://vagrant.dev.schoolbox.cloud/rubric/determineMarkInvalidation
        $this->guzzle_headers['Referer'] = 'https://'.$this::VAGRANT_DOMAIN.'/learning/assessment/'.$assignmentID.'/modify?returnUrl=';


        $page='/rubric/determineMarkInvalidation';
        $url = 'https://'.$this::VAGRANT_DOMAIN.$page;

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

    public function updateAssessemntOnHomepage($HomePageID, $assignmentID, $assignmentTitle)
    {
        if (!isset($this->client))
        {
            $this->initGuzzleClient();
        }

        //https://vagrant.dev.schoolbox.cloud/learning/assessments/381
        $this->guzzle_headers['Referer'] = 'https://'.$this::VAGRANT_DOMAIN.'/learning/assessment/'.$assignmentID.'/modify?returnUrl=';

        $page='/learning/assessments/'.$assignmentID;
        $url = 'https://'.$this::VAGRANT_DOMAIN.$page;

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

    public function createAssessementForHomepageWithRubric($title, $HomePageID)
    {

        if (!isset($this->client))
        {
            $this->initGuzzleClient();
        }

        $this->guzzle_headers['Referer'] = 'https://'.$this::VAGRANT_DOMAIN.'/learning/folders/'.$HomePageID.'/new?returnUrl=';

        $page='/rubric';
        $url = 'https://'.$this::VAGRANT_DOMAIN.$page;

        $allow_redirects=false;

        $capture_criterion_group_id='/rb\[0\]\[criterion\]\[(\d+)]\[groupId\]" value="(\d+)/';
        #So that we can loop through getting the ID's for components create a skelleton to allow us to get it done. Need to update all the - values with real values.
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

        $copySkeletonForItteration = $rubySkeleton;
        $counter=0;
        foreach ($copySkeletonForItteration[0]['group'] as $group)
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
                $response = $this->client->request('POST', $url, [
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
            foreach ($copySkeletonForItteration[0]['criterion'] as $criterion)
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
                if($counterCriterion==2){
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

        $this->guzzle_headers['Referer'] = 'https://'.$this::VAGRANT_DOMAIN.'/learning/folders/'.$HomePageID.'/assessments/new?type=dueWork';

        $page='/learning/folders/'.$HomePageID.'/assessments';
        $url = 'https://'.$this::VAGRANT_DOMAIN.$page;

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

    public function submitAssessment($assessementID, $user_id)
    {
        //https://vagrant.dev.schoolbox.cloud/learning/assessments/'.$assignmentID.'/'.$user_id.'/submit
        //https://vagrant.dev.schoolbox.cloud/learning/assessments/'.$assignmentID.'/'.$user_id.'/submit

        if (!isset($this->client))
        {
            $this->initGuzzleClient();
        }

        $this->guzzle_headers['Referer'] = 'https://'.$this::VAGRANT_DOMAIN.'/learning/assessments/'.$assessementID.'/'.$user_id.'/submit';

        $page='/learning/assessments/'.$assessementID.'/'.$user_id.'/submit';
        $url = 'https://'.$this::VAGRANT_DOMAIN.$page;

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

    public function markAssessment($assessementID, $user_marking_id, $student_submitting_id)
    {
        if (!isset($this->client))
        {
            $this->initGuzzleClient();
        }

        $submissionID = $this->getFirstSubmissionIdByOwnerAndAssessmentID($assessementID, $user_marking_id, $student_submitting_id);

        $this->guzzle_headers['Referer'] = 'https://'.$this::VAGRANT_DOMAIN.'/learning/assessments/'.$assessementID.'/'.$student_submitting_id.'/responses';

        $page='/learning/assessments/'.$assessementID.'/'.$student_submitting_id.'/'.$submissionID.'/feedback';
        $url = 'https://'.$this::VAGRANT_DOMAIN.$page;

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

    public function markAssessmentWithRubric($assessementID, $user_marking_id, $student_submitting_id)
    {
        if (!isset($this->client))
        {
            $this->initGuzzleClient();
        }

        $submissionID = $this->getFirstSubmissionIdByOwnerAndAssessmentID($assessementID, $user_marking_id, $student_submitting_id);
        $markGrid = $this->getAssessmentRubricGrid($assessementID); ## Has been deprecated needs a recode.

        $this->guzzle_headers['Referer'] = 'https://'.$this::VAGRANT_DOMAIN.'/learning/assessments/'.$assessementID.'/'.$student_submitting_id.'/responses';

        $page='/learning/assessments/'.$assessementID.'/'.$student_submitting_id.'/'.$submissionID.'/feedback';
        $url = 'https://'.$this::VAGRANT_DOMAIN.$page;

        $allow_redirects = $this->redirect_settings;

        $rubric_data=[];

        foreach ($markGrid->getCapabilityUsages() as $group_capability) {
            foreach ($group_capability->getIndicatorUsages() as $capability_indicator)
            {
                $decriptors = $capability_indicator->getDescriptors();
                $levelToPick=rand(0,2);
                $rubric_data['descriptors'][$decriptors[$levelToPick]->getId()]['selected']= 1;
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

    public function draftNews($title)
    {
        if (!isset($this->client))
        {
            $this->initGuzzleClient();
        }

        $this->guzzle_headers['Referer'] = 'https://'.$this::VAGRANT_DOMAIN.'/news/create';

        $page='/news/create';
        $url = 'https://'.$this::VAGRANT_DOMAIN.$page;

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

    public function createNewsToModerate($title, $news_id)
    {
        if (!isset($this->client))
        {
            $this->initGuzzleClient();
        }

        $this->guzzle_headers['Referer'] = 'https://'.$this::VAGRANT_DOMAIN.'/news/'.$news_id.'/update';

        $page='/news/moderation/'.$news_id.'/submit';
        $url = 'https://'.$this::VAGRANT_DOMAIN.$page;

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

    public function publishModeratedNewsByID($news_id)
    {
        if (!isset($this->client))
        {
            $this->initGuzzleClient();
        }

        $this->guzzle_headers['Referer'] = 'https://'.$this::VAGRANT_DOMAIN.'/news/'.$news_id.'/update';

        $page='/news/moderation/'.$news_id.'/accept';
        $url = 'https://'.$this::VAGRANT_DOMAIN.$page;

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

    public function saveNewsByIDforLater($news_id)
    {
        #/saved/20/add
        if (!isset($this->client))
        {
            $this->initGuzzleClient();
        }

        $this->guzzle_headers['Referer'] = 'https://'.$this::VAGRANT_DOMAIN.'/news';

        $page='/news/saved/'.$news_id.'/add';
        $url = 'https://'.$this::VAGRANT_DOMAIN.$page;

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

    public function rejectNewsByID($news_id)
    {
        if (!isset($this->client))
        {
            $this->initGuzzleClient();
        }

        $this->guzzle_headers['Referer'] = 'https://'.$this::VAGRANT_DOMAIN.'/news/'.$news_id.'/update';

        $page='/news/moderation/'.$news_id.'/reject';
        $url = 'https://'.$this::VAGRANT_DOMAIN.$page;

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

    public function deleteNewsByID($news_id)
    {

        if (!isset($this->client))
        {
            $this->initGuzzleClient();
        }

        $this->guzzle_headers['Referer'] = 'https://'.$this::VAGRANT_DOMAIN.'/news/'.$news_id.'/update';

        $page='/news/'.$news_id.'/delete';
        $url = 'https://'.$this::VAGRANT_DOMAIN.$page;

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

    public function publishNewsForm($title, $news_id)
    {

        if (!isset($this->client))
        {
            $this->initGuzzleClient();
        }

        $this->guzzle_headers['Referer'] = 'https://'.$this::VAGRANT_DOMAIN.'/news/'.$news_id.'/update';

        $page='/news/moderation/'.$news_id.'/accept';
        $url = 'https://'.$this::VAGRANT_DOMAIN.$page;

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

    public function loginAdmin()
    {
        $admin_username='alaress';
        $admin_password='alaress';
        $response = $this->loginUser($admin_username, $admin_password, false);
        if ($response->getStatusCode() == 303)
        {
            return true;
        }
        throw (new Exception('Unable to login for some reason'));
    }
}
