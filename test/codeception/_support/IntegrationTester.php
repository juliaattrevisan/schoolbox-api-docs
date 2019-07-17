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
use Schoolbox\Resource\Textbox\Mapper as TextboxMapper;
use Schoolbox\Comms\News\Mapper as NewsMapper;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Mapper as UnitMapper;
use Schoolbox\Learning\Course\DataModel\Specification\Mapper as CourseSpecificationMapper;
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
    private const VAGRANT_DOMAIN = 'vagrant.dev.schoolbox.cloud';
    public const HOMEPAGE_COMPONENT_POSITION = ['left'=>'1','right'=>'2','top'=>'3'];

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

    public function getComponentByClass($element_class)
    {
        if(!isset($this->database)) {
            $this->initDatabase();
        }
        return Component_DefinitionMapper::getInstance()->findOneByColumn('class',$element_class);
    }

    public function getComponentOnHomepage($homepage_id, $component, $position)
    {
        if(!isset($this->database)) {
            $this->initDatabase();
        }
        return Component_InstanceMapper::getInstance()->findOneByColumnList(['component_definition_id'=>$component->getId(),
                                                                            'module'=>'homepage',
                                                                            'identifier'=>$homepage_id,
                                                                            'position'=>$position]);
    }

    public function getTextBoxByHeading($title)
    {
        if(!isset($this->database)) {
            $this->initDatabase();
        }
        return TextBoxMapper::getInstance()->findOneByColumnList(['heading'=>$title, 'deleted' => null]);
    }

    function getNewsArticle($title, $latest_id=true){

        if(!isset($this->database)) {
            $this->initDatabase();
        }
        return NewsMapper::getInstance()->findOneByColumn('title', $title);
    }

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
        return $this->getFirstFolderIdByTitle($title)->getId();
    }

    function getFirstFolderByTitle($title)
    {
        if(!isset($this->database)) {
            $this->initDatabase();
        }
        /** @var Schoolbox\Resource\Folder\Model $folder */
        return FolderMapper::getInstance()->findOneByColumn('name', $title);
    }

    function getFirstCourseByTitle($title)
    {
        if(!isset($this->database)) {
            $this->initDatabase();
        }
        $course_folder = $this->getFirstFolderByTitle($title);
        return CourseSpecificationMapper::getInstance()->findOneByColumn('folder_id', $course_folder->getId());
    }

    function getFirstUnitByTitle($title)
    {
        if(!isset($this->database)) {
            $this->initDatabase();
        }
        $unit_folder = $this->getFirstFolderByTitle($title);
        return UnitMapper::getInstance()->findOneByColumn('folder_id', $unit_folder->getId());
    }

    function getFirstAssessmentByTitle($title)
    {
        if(!isset($this->database)) {
            $this->initDatabase();
        }
        return AssessmentMapper::getInstance()->findOneByColumn('title', $title);
    }

    function getFirstAssessmentIdByTitle($title)
    {
        if(!isset($this->database)) {
            $this->initDatabase();
        }
        /** @var Schoolbox\Learning\Assessment\DataModel\Mapper $DueWork */
        $assessment = $this->getFirstAssessmentByTitle($title);
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

    public function loginForgotPasswordUsername($username, $redirect=true)
    {
        if (!isset($this->client))
        {
            $this->initGuzzleClient();
        }

        $this->guzzle_headers['Referer'] = 'https://'.$this::VAGRANT_DOMAIN.'/passwordReset';

        $page='/passwordReset/createRequest';
        $url = 'https://'.$this::VAGRANT_DOMAIN.$page;
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

    public function loginForgotPasswordEmail($email, $redirect=true)
    {
        return $this->loginForgotPasswordUsername($email);
    }

    private function createNewCourseSpec($name, $parent_folder)
    {
        if (!isset($this->client))
        {
            $this->initGuzzleClient();
        }

        $this->guzzle_headers['Referer'] = 'https://'.$this::VAGRANT_DOMAIN.'/course/new';

        $page='/course/new';
        $url = 'https://'.$this::VAGRANT_DOMAIN.$page;
        $data =[
            'name' => $name,
            'parent' => $parent_folder->getId(),
            'curriculumCodes' => [''],
            'part1'=> 1
        ];

        $allow_redirects = $this->redirect_settings;

        $this->client->request('POST', $url, [
            'headers' => $this->guzzle_headers,
            'allow_redirects' => $allow_redirects,
            'cookies'=>$this->client_cookie_jar,
            'form_params'=>$data
        ]);
    }

    private function createNewUnitSpec($name, $course)
    {
        if (!isset($this->client))
        {
            $this->initGuzzleClient();
        }

        $this->guzzle_headers['Referer'] = 'https://'.$this::VAGRANT_DOMAIN.'/course/modify/'.$course->getId();

        $page='/course/'.$course->getId().'/unitspecs/new';
        $url = 'https://'.$this::VAGRANT_DOMAIN.$page;
        $data =[
            'name' => $name
        ];

        $allow_redirects = $this->redirect_settings;

        try {
            $this->client->request('POST', $url, [
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

    private function saveCourseSpec($course_name, $unit_name, $parentId, $course_spec, $unit){
        if (!isset($this->client))
        {
            $this->initGuzzleClient();
        }

        $this->guzzle_headers['Referer'] = 'https://'.$this::VAGRANT_DOMAIN.'/course/modify/'.$course_spec->getId();

        $page='/course/modify/'.$course_spec->getId();
        $url = 'https://'.$this::VAGRANT_DOMAIN.$page;
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

    public function createCourseWithEmptyUnit($title, $parent_folder)
    {
        $this->createNewCourseSpec($title, $parent_folder);
        $course_spec = $this->getFirstCourseByTitle($title);
        $unit_name=$title.' EmptyUnit';
        $this->createNewUnitSpec($unit_name, $course_spec);
        $unit = $this->getFirstUnitByTitle($unit_name);
        return $this->saveCourseSpec($title, $unit_name, $parent_folder->getId(), $course_spec, $unit);
    }

    public function createCourseWithTestUnits($title, $parent_folder)
    {
        $this->createNewCourseSpec($title, $parent_folder);
        $course_spec = $this->getFirstCourseByTitle($title);
        $unit_name=$title.' UnitWithAssessments';
        $this->createNewUnitSpec($unit_name, $course_spec);
        $unit_folder = $this->getFirstFolderByTitle($unit_name);
        $unit = $this->getFirstUnitByTitle($unit_name);
        $this->createAssessemntOnCourseUnitSpec('Assessment for course due work', $unit->getId(), $unit_folder->getId());
        # TODO Project name after first test run through is not unique, might need to rework what is used to fetch it.
        $this->createAssessemntProjectOnCourseUnitSpec('Assessment for course project', $unit->getId(), $unit_folder->getId());
        $project_assessment_id=$this->getFirstAssessmentIdByTitle('Assessment for course project');
        $this->createAssessemntOnProject('Assessment for project in course', $project_assessment_id, $unit_folder->getId());
        return $this->saveCourseSpec($title, $unit_name, $parent_folder->getId(), $course_spec, $unit);
    }

    public function createHomepage($title)
    {
        return $this->createFolder($title, 9);
    }

    public function createFolder($title, $template=-1, $parent_folder=null)
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

        $this->guzzle_headers['Referer'] = 'https://'.$this::VAGRANT_DOMAIN.'/resource/folder/create/'.$parent_id;

        $page='/resource/folder/update';
        $url = 'https://'.$this::VAGRANT_DOMAIN.$page;

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
            'code'=>'',
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


    public function addUsersToHomepageGroup($homepage_id, $user_ids)
    {
        if (!isset($this->client))
        {
            $this->initGuzzleClient();
        }

        $this->guzzle_headers['Referer'] = 'https://'.$this::VAGRANT_DOMAIN.'/group/modify/'.$homepage_id;

        $page='/group/modifyForm.php';
        $url = 'https://'.$this::VAGRANT_DOMAIN.$page;
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

    public function setHomepageGroupUserPermission($homepage_id, $user_id_permission_array)
    {
        if (!isset($this->client))
        {
            $this->initGuzzleClient();
        }

        $this->guzzle_headers['Referer'] = 'https://'.$this::VAGRANT_DOMAIN.'/group/modify/'.$homepage_id;

        $page='/group/modifyForm.php';
        $url = 'https://'.$this::VAGRANT_DOMAIN.$page;

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

    public function addDefaultUsersToHomepage($homepage_id)
    {
        if (!isset($this->client))
        {
            $this->initGuzzleClient();
        }

        $this->addUsersToHomepageGroup($homepage_id, [230, 151]);
        $this->setHomepageGroupUserPermission($homepage_id, ['151'=>3]);
    }

    public function addComponentToHomepage($element_id, $homepage_id, $location)
    {
        if (!isset($this->client))
        {
            $this->initGuzzleClient();
        }

        $this->guzzle_headers['Referer'] = 'https://'.$this::VAGRANT_DOMAIN.'/homepage/'.$homepage_id;

        $page='/homepage/configForm.php';
        $url = 'https://'.$this::VAGRANT_DOMAIN.$page;

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

    public function addTextBoxToTextComponent($homepage_id, $text_component, $text, $heading)
    {

        if (!isset($this->client))
        {
            $this->initGuzzleClient();
        }

        //https://vagrant.dev.schoolbox.cloud/homepage/560/textBoxCreate/3679
        $this->guzzle_headers['Referer'] = 'https://'.$this::VAGRANT_DOMAIN.'/homepage/'.$homepage_id.'/textBoxCreate/'.$text_component->getId();

        $page='/textbox/textBoxForm.php';
        $url = 'https://'.$this::VAGRANT_DOMAIN.$page;

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

    public function deleteTextBoxToTextComponent($homepage_id, $heading)
    {
        if (!isset($this->client))
        {
            $this->initGuzzleClient();
        }

        $textBox = $this->getTextBoxByHeading($heading);

        $this->guzzle_headers['Referer'] = 'https://'.$this::VAGRANT_DOMAIN.'/homepage/'.$homepage_id;

        $page='/textbox/textBoxForm.php';
        $url = 'https://'.$this::VAGRANT_DOMAIN.$page;

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

    public function createAssessemnt($contexttype, $contextId, $folderId, $title, $assessmentType)
    {
        if (!isset($this->client))
        {
            $this->initGuzzleClient();
        }

        $this->guzzle_headers['Referer'] = 'https://'.$this::VAGRANT_DOMAIN.'/learning/'.$contexttype.'/'.$contextId.'/assessments/new?type=='.$assessmentType;

        $page='/learning/'.$contexttype.'/'.$contextId.'/assessments';
        $url = 'https://'.$this::VAGRANT_DOMAIN.$page;

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

        if ($assessmentType == 'project')
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

    public function createAssessemntOnHomepage($title, $HomePageID)
    {
        return $this->createAssessemnt('folders', $HomePageID, $HomePageID, $title, 'dueWork');
    }

    public function createAssessemntOnCourseUnitSpec($title, $unit_id, $unit_folder_id)
    {
        return $this->createAssessemnt('unitspecs', $unit_id, $unit_folder_id, $title, 'dueWork');
    }

    public function createAssessemntProjectOnCourseUnitSpec($title, $unit_id, $unit_folder_id)
    {
        return $this->createAssessemnt('unitspecs', $unit_id, $unit_folder_id, $title, 'project');
    }

    public function createAssessemntOnProject($title, $project_id, $unit_folder_id)
    {
        return $this->createAssessemnt('projects', $project_id, $unit_folder_id, $title, 'dueWork');
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
