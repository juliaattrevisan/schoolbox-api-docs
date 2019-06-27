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

    public function loginUser($username, $password)
    {
        $headers = ['Host' => 'vagrant.dev.schoolbox.cloud',
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Referer'=>'https://vagrant.dev.schoolbox.cloud/login/'];
        $page='/login/';
        $url = 'https://vagrant.dev.schoolbox.cloud'.$page;
        $data =[
            'page' => '/login/',
            'username' => $username,
            'password' => $password,
            'Submit'=> 'Login'
        ];
        $jar = new CookieJar();

        $allow_redirects=[
            'max'             => 5,
            'referer'         => true,
            'protocols'       => ['http', 'https'],
            'track_redirects' => true
        ];

        $client = new Client(['base_uri' => 'https://vendor.dev.schoolbox.cloud', 'timeout' => 5.0, 'cookies' => true]);
        return $client->request('POST', $url, [
                            'headers'=>$headers,
                            'query' =>'page=/login/',
                            'form_params' => $data,
                            'cookies' => $jar,
                            'allow_redirects' => $allow_redirects
            ]);
    }

    public function loginUserForCookies($jar, $username, $password)
    {
        $headers = ['Host' => 'vagrant.dev.schoolbox.cloud',
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Referer'=>'https://vagrant.dev.schoolbox.cloud/login/'];
        $page='/login/';
        $url = 'https://vagrant.dev.schoolbox.cloud'.$page;
        $data =[
            'page' => '/login/',
            'username' => $username,
            'password' => $password,
            'Submit'=> 'Login'
        ];

        $allow_redirects=[
            'max'             => 5,
            'referer'         => true,
            'protocols'       => ['http', 'https'],
            'track_redirects' => true
        ];

        $client = new Client(['base_uri' => 'https://vendor.dev.schoolbox.cloud', 'timeout' => 5.0, 'cookies' => true]);
        $client->request('GET', 'https://vagrant.dev.schoolbox.cloud', [
            'cookies' => $jar,
            'allow_redirects' => $allow_redirects
        ]);

        return $client->request('POST', $url, [
            'headers'=>$headers,
            'query' =>'page=/login/',
            'form_params' => $data,
            'allow_redirects' => $allow_redirects
        ]);
    }

    public function draftNews($session_cookies, $title)
    {
        $new_cookie=[
            'Name'=>$session_cookies[0]['Name'],
            'Value'=>$session_cookies[0]['Value'],
            'Domain'=>$session_cookies[0]['Domain'],
            'Max-Age'=>$session_cookies[0]['Max-Age'],
            'Expires'=>$session_cookies[0]['Expires'],
            'Path'=>$session_cookies[0]['Path'],
            'HttpOnly'=>$session_cookies[0]['HttpOnly']
        ];
        $jar = new CookieJar();
        $jar->setCookie(new GuzzleHttp\Cookie\SetCookie($new_cookie));

        $headers = ['Host' => 'vagrant.dev.schoolbox.cloud',
                    'Content-Type'=>'application/x-www-form-urlencoded; charset=UTF-8',
                    'Accept'=>'application/json, text/javascript, */*; q=0.01',
                    'Referer'=>'vagrant.dev.schoolbox.cloud/news/create';

        $page='/news/create';
        $url = 'https://vagrant.dev.schoolbox.cloud'.$page;



        $allow_redirects=[
            'max'             => 5,
            'referer'         => true,
            'protocols'       => ['http', 'https'],
            'track_redirects' => true
        ];

        $data =[
            'returnUrl' => Querystring::encode('/'),
            'title' => $title,
            'body' => '',
            'blurb' => '',
            'audience' => '',
            'publishAt' => '26/06/2019+1:47pm',
            'expiresAt' => '03/07/2019+11:59pm',
            'draft'=> true
        ];


        $client = new Client(['base_uri' => 'https://vendor.dev.schoolbox.cloud', 'timeout' => 5.0, 'cookies' => true]);
        try {
            return $client->request('POST', $url, [
                'headers' => $headers,
                'allow_redirects' => $allow_redirects,
                'cookies'=>$jar,
                'form_params'=>$data
            ]);
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            print($e->getMessage());
            print($e->getResponse()->getBody()->getContents());
            throw $e;
        }

    }

    public function loginAdminGetToken()
    {
        $admin_username='alaress';
        $admin_password='alaress';
        $mycookies = new CookieJar();
        $response = $this->loginUserForCookies($mycookies, $admin_username, $admin_password);
        if ($mycookies->getCookieByName('PHPSESSID'))
        {
            return $mycookies->toArray();
        }
        throw (new Exception('Unable to login for some reason'));
    }
}
