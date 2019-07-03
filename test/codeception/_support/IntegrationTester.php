<?php

use Alaress\Redis;
use Aura\Di\ContainerInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7;
use GuzzleHttp\Cookie\CookieJar;
use Schoolbox\Comms\News\Querystring;
use Schoolbox\Realtime\MessageList\ListFactory;
use Schoolbox\Realtime\Notification\Decorator;
use Schoolbox\UserMapper;
use Schoolbox\Comms\News\Mapper as NewsMapper;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\User;

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
    public $client_cookie_jar;
    public $guzzle_headers=[];
    public $redirect_settings;

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

    function getUser($username){

        $database=Database::getInstance();
        $database->connect('mysql:host=localhost;dbname=schoolbox', 'root', 'tnp879');
        return UserMapper::getInstance()->findOneByColumn('username', $username);
    }

    function getNewsArticle($title, $latest_id=true){
        $database=Database::getInstance();
        $database->connect('mysql:host=localhost;dbname=schoolbox', 'root', 'tnp879');
        return NewsMapper::getInstance()->findOneByColumn('title', $title);
    }

    function getFirstHomepageIdByTitle($title)
    {
        $database=Database::getInstance();
        $database->connect('mysql:host=localhost;dbname=schoolbox', 'root', 'tnp879');
        return FolderMapper::getInstance()->findOneByColumn('title', $title);
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

    public function createAssessemntHomepage($title, $HomePageID)
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
            'title' => 'A Test Assignment',
            'description' => '<p>This is a test assignment for automated tests</p>',
            'acceptOnline' => 0,
            'publishDate' => '01/07/2019 12:00am',
            'openDate' => '01/07/2019 12:00am',
            'dueDate' => '02/07/2019 11:59pm',
            'closeDate' => '02/07/2019 11:59pm',
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

    public function determineMarkInvalidation($HomePageID, $assignmentID)
    {
        if (!isset($this->client))
        {
            $this->initGuzzleClient();
        }

        //https://vagrant.dev.schoolbox.cloud/rubric/determineMarkInvalidation
        $this->guzzle_headers['Referer'] = 'https://'.$this::VAGRANT_DOMAIN.'/learning/folders/'.$HomePageID.'/assessments/new?type=dueWork';

        $page='/learning/folders/'.$HomePageID.'/assessments';
        $url = 'https://'.$this::VAGRANT_DOMAIN.$page;

        $allow_redirects = $this->redirect_settings;

        /*
         *assessmentType=dueWork
            returnUrl=L2xlYXJuaW5nL2Fzc2Vzc21lbnRzLzM4MS8xOQ==
            id=381
            folderId=552
            title=A+Test+Assignment
            workType
            description=<p>afdsgadfg</p>

            acceptOnline=0
            publishDate=01/07/2019+12:00am
            openDate=01/07/2019+12:00am
            dueDate=02/07/2019+11:59pm
            closeDate=02/07/2019+11:59pm
            weight=13
            self_reviewable=none
            visibility=2
            assessmentType=dueWork
            markType=1
            maxMark
            gradingScheme=1
            resultset=1
            formField=rb
            rb[381][level][0][id]
            rb[381][level][0][name]
            rb[381][level][1][id]
            rb[381][level][1][name]
            curriculumCodes[]
         *
        */

        $data =[
            'contextType' => 'folders',
            'contextId' => $HomePageID,
            'folderId' => $HomePageID,
            'assessmentType' => 'dueWork',
            'title' => 'A Test Assignment',
            'description' => '<p>This is a test assignment for automated tests</p>',
            'acceptOnline' => 0,
            'publishDate' => '01/07/2019 12:00am',
            'openDate' => '01/07/2019 12:00am',
            'dueDate' => '02/07/2019 11:59pm',
            'closeDate' => '02/07/2019 11:59pm',
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

    public function updateAssessemntHomepage($HomePageID, $assignmentID)
    {
        if (!isset($this->client))
        {
            $this->initGuzzleClient();
        }

        //https://vagrant.dev.schoolbox.cloud/learning/assessments/381
        $this->guzzle_headers['Referer'] = 'https://'.$this::VAGRANT_DOMAIN.'/learning/folders/'.$HomePageID.'/assessments/new?type=dueWork';

        $page='/learning/folders/'.$HomePageID.'/assessments';
        $url = 'https://'.$this::VAGRANT_DOMAIN.$page;

        $allow_redirects = $this->redirect_settings;

        /*
         *
         * assessmentType=dueWork
            returnUrl=L2xlYXJuaW5nL2Fzc2Vzc21lbnRzLzM4MS8xOQ==
            id=381
            folderId=552
            title=A+Test+Assignment
            workType
            description=<p>afdsgadfg</p>

            acceptOnline=0
            publishDate=01/07/2019+12:00am
            openDate=01/07/2019+12:00am
            dueDate=02/07/2019+11:59pm
            closeDate=02/07/2019+11:59pm
            weight=13
            self_reviewable=none
            visibility=2
            assessmentType=dueWork
            markType=1
            maxMark
            gradingScheme=1
            resultset=1
            formField=rb
            rb[381][level][0][id]
            rb[381][level][0][name]
            rb[381][level][1][id]
            rb[381][level][1][name]
            curriculumCodes[]
            curriculumCodes[]
            part-2=1
         */
        $data =[
            'contextType' => 'folders',
            'contextId' => $HomePageID,
            'folderId' => $HomePageID,
            'assessmentType' => 'dueWork',
            'title' => 'A Test Assignment',
            'description' => '<p>This is a test assignment for automated tests</p>',
            'acceptOnline' => 0,
            'publishDate' => '01/07/2019 12:00am',
            'openDate' => '01/07/2019 12:00am',
            'dueDate' => '02/07/2019 11:59pm',
            'closeDate' => '02/07/2019 11:59pm',
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

    public function publishNewsByID($news_id)
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
