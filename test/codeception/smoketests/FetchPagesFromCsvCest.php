<?php


namespace SmokeTests;


use Codeception\Exception\ConditionalAssertionFailed;
use IntegrationTester;
use Payload;

class FetchPagesFromCsvCest
{
    #Need some constants to represent users or logic in the before that sets the users.
    protected $personas = [
        'Superuser'              => ['username' => 'alaress', 'password' => 'alaress'],
        'Admin'                  => ['username' => 'alaress', 'password' => 'alaress'],
        'Teacher'                => ['username' => 'jcoles', 'password' => 'password'],
        'Student'                => ['username' => 'jreed', 'password' => 'password'],
        'Pastoral Moderator'     => ['username' => 'jcoles', 'password' => 'password'],
        'Fixtures Administrator' => ['username' => 'jcoles', 'password' => 'password'],
        'News Moderator'         => ['username' => 'rwhite', 'password' => 'password'],
    ];

    /**
     * @param IntegrationTester $i
     */
    public function _before(IntegrationTester $i)
    {
        #Might not need this login any more as I think this runs each test, so the login differs each test now.
        #$i->loginAdmin(); #we logged in as alaress
    }

    /**
     * Fail test to ensure we can get a 404 response from schoolbox.
     *
     * @param IntegrationTester $i
     */
    public function getFailure404(IntegrationTester $i)
    {
        #GIVEN
        $page                 = '/i_dont_exist_at_all_anywhere';
        $student_user_details = $this->personas['Student'];

        #WHEN
        $this->loginUser($i, $student_user_details['username'], $student_user_details['password']);

        $getPayload = new Payload\Base('GET', '/', $page);
        $i->sendRequest($getPayload);

        #THEN
        $i->seeResponseCodeIs(404);
    }

    /**
     * Fail test to ensure we can get a permission denied from schoolbox using sitepage
     *
     * @param IntegrationTester $i
     */
    public function getFailureSitePagePermissionDenied(IntegrationTester $i)
    {
        #GIVEN
        $page                 = '/adminv2/home';
        $student_user_details = $this->personas['Student'];

        #WHEN
        $this->loginUser($i, $student_user_details['username'], $student_user_details['password']);

        $getPayload = new Payload\Base('GET', '/', $page);
        $i->sendRequest($getPayload);

        #THEN
        $i->seeResponseCodeIs(401);
    }

    /**
     * Fail test to ensure we can get a permission denied from schoolbox using Aura
     *
     * @param IntegrationTester $i
     */
    public function getFailureAuraPermissionDenied(IntegrationTester $i)
    {
        $page                 = '/adminv2/setting';
        $student_user_details = $this->personas['Student'];

        #WHEN
        $this->loginUser($i, $student_user_details['username'], $student_user_details['password']);

        $getPayload = new Payload\Base('GET', '/', $page);
        $i->sendRequest($getPayload);

        #THEN
        $i->seeResponseCodeIs(401);
    }

    /** Get all the pages, uses the url provider, data that is read from a csv to fetch all the pages stated and
     * checks that all pages come back with a 200 ok response.
     *
     * @dataProvider urlProvider
     * @skip
     * 
     */
    public function getOldAllThePages(IntegrationTester $i, \Codeception\Example $example)
    {
        #GIVEN
        $persona      = $example[1];
        $url          = $example[3];
        $user_details = $this->personas[$persona];

        #Need to login to the right user
        $this->loginUser($i, $user_details['username'], $user_details['password']);

        #proceed to test the url which is below.
        $getPayload = new Payload\Base('GET', '/', $url);

        #WHEN
        $i->sendRequest($getPayload);

        #THEN
        $i->seeResponseCodeIs(200);
    }

    /** Url Provider - reads a csv file to provide an array of data for the fetch all pages test.
     *
     * GetSmokeTestList csv is formatted a specific way for the test.
     * Module, Persona, Action, URL
     *
     * Module is not used, just for file readability.
     * Persona selects the user to login with
     * Action not used in the test, gives context as to the action on the csv.
     * URL is what we fetch.
     *
     * @return array - [string Module, string Persona, string Action, string URL]
     */
    protected function urlProvider()
    {
        $csvData = array_map('str_getcsv', file(codecept_data_dir('/SmokeTests/GetSmokeTestList.csv')));

        return $csvData;
    }

    /**
     * login the specific user. Used during the test for fetching URLs on the csv
     *
     * @param IntegrationTester $i
     * @param string            $username
     * @param string            $password
     */
    protected function loginUser(IntegrationTester $i, string $username, string $password)
    {
        $loginPayload = new Payload\Authentication\Login();
        $loginPayload->setUsername($username);
        $loginPayload->setPassword($password);

        $i->sendRequest($loginPayload);
    }
    
    /** Get all the pages, uses the url provider, data that is read from a csv to fetch all the pages stated and
     * checks that all pages come back with a 200 ok response.
     *
     * @dataProvider newUrlProvider
     * 
     */
    public function getAllThePages(IntegrationTester $i, $senario, \Codeception\Example $example)
    {
        #GIVEN
        $persona      = $example[2];
        $url          = $example[3];
        $expected_result = $example[4];
        
        if($expected_result=='IGNORE')
        {
            $senario->skip('Test ignored');
        }
        
        $user_details = $this->personas[$persona];
        
        #Need to login to the right user
        $this->loginUser($i, $user_details['username'], $user_details['password']);
        
        #proceed to test the url which is below.
        $getPayload = new Payload\Base('GET', '/', $url);
        
        #WHEN
        $i->sendRequest($getPayload);
        
        #THEN
        $i->seeResponseCodeIs($expected_result);
    }
    
    /** New Url Provider - reads a csv file to provide an array of data for the fetch all pages test.
     *
     * GetSmokeTestList csv is formatted a specific way for the test.
     * Module, Persona, Action, URL
     *
     * Module is not used, just for file readability.
     * Persona selects the user to login with
     * Action not used in the test, gives context as to the action on the csv.
     * URL is what we fetch.
     *
     * @return array - [string Module, string Persona, string Action, string URL]
     */
    protected function newUrlProvider()
    {
        $csvData = array_map('str_getcsv', file(codecept_data_dir('/SmokeTests/GetSmokeTestDataList.csv')));
        
        return $csvData;
    }
    
}


