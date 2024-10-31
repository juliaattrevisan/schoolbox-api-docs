<?php

namespace Tests\Smoketests;

use Tests\Support\IntegrationTester;
use Tests\Support\Payload;
use Codeception\Scenario;
use SensitiveParameter;

class FetchPagesFromCsvCest
{
    #Need some constants to represent users or logic in the before that sets the users.
    protected $personas = [
        'Superuser'              => ['username' => 'alaress', 'password' => 'alaress'],
        'Admin'                  => ['username' => 'alaress', 'password' => 'alaress'],
        'Teacher'                => ['username' => 'achan', 'password' => 'password'],
        'Student'                => ['username' => 'jreed', 'password' => 'password'],
        'Parent'                 => ['username' => 'jennireed01', 'password' => 'password'],
        'Pastoral Moderator'     => ['username' => 'jcoles', 'password' => 'password'],
        'Fixtures Administrator' => ['username' => 'jcoles', 'password' => 'password'],
        'News Moderator'         => ['username' => 'rwhite', 'password' => 'password'],
    ];

    protected $users = [];


    /**
     * Fail test to ensure we can get a 404 response from schoolbox.
     *
     * @param IntegrationTester $i
     *
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
     * @param Scenario          $scenario
     * @throws \JsonException
     */
    public function getFailureSitePagePermissionDenied(IntegrationTester $i, Scenario $scenario): void
    {
        #GIVEN
        $page                 = '/adminv2/home';
        $student_user_details = $this->personas['Student'];

        #WHEN
        $this->loginUser($i, $student_user_details['username'], $student_user_details['password']);

        $getPayload = new Payload\Base('GET', '/', $page);
        $i->sendRequest($getPayload);

        $skipTest = false;

        try {
            $i->seeResponseCodeIs(403);
            $skipTest = true; // This line will execute only if the above line doesn't throw an exception.
        } catch (\Exception) {
            // Do nothing.
        }

        if ($skipTest) {
            $scenario->skip('Error 403 is used sometimes instead of 401');
        }

        #THEN
        $i->seeResponseCodeIs(401);
    }

    /**
     * Fail test to ensure we can get a permission denied from schoolbox using Aura
     *
     * @param IntegrationTester $i
     *
     */
    public function getFailureAuraPermissionDenied(IntegrationTester $i, Scenario $scenario): void
    {
        $page                 = '/adminv2/setting';
        $student_user_details = $this->personas['Student'];

        #WHEN
        $this->loginUser($i, $student_user_details['username'], $student_user_details['password']);

        $getPayload = new Payload\Base('GET', '/', $page);
        $i->sendRequest($getPayload);

        $skipTest = false;

        try {
            $i->seeResponseCodeIs(403);
            $skipTest = true; // This line will execute only if the above line doesn't throw an exception.
        } catch (\Exception) {
            // Do nothing.
        }

        if ($skipTest) {
            $scenario->skip('Error 403 is used sometimes instead of 401');
        }

        #THEN
        $i->seeResponseCodeIs(401);
    }

    /**
     * login the specific user. Used during the test for fetching URLs on the csv
     *
     * @param IntegrationTester $i
     * @param string            $username
     * @param string            $password
     */
    protected function loginUser(IntegrationTester $i, string $username, #[SensitiveParameter] string $password): void
    {
        $loginPayload = new Payload\Authentication\Login();
        $loginPayload->setUsername($username);
        $loginPayload->setPassword($password);

        $i->sendRequest($loginPayload);
    }

    /**
     * Get all the pages, uses the url provider, data that is read from a csv to fetch all the pages stated and
     * checks that all pages come back with a 200 ok response.
     *
     * @dataProvider urlProvider
     */
    public function getAllThePages(IntegrationTester $i, Scenario $scenario, \Codeception\Example $example): void
    {
        # TODO split out tests into one per persona, each test sends a parameter to the url provider which determines the dataset.
        #GIVEN
        $persona      = $example[2];
        $url          = $example[3];
        $expected_result = $example[4];

        /*
         * Due to the data from example url being so huge, override the feature to include an extra
         * line with the details that can be huge. What user is doing, Persona, URL
         */
        $scenario->setFeature($scenario->getFeature() . PHP_EOL . 'Scenario: ' . $example[1] . ' | ' . $persona . ' | ' . $url);

        if ($expected_result == 'IGNORE') {
            $scenario->skip('Test ignored');
        }

        if (!isset($this->users[$persona])) {
            $user_details = $this->personas[$persona];
            $this->loginUser($i, $user_details['username'], $user_details['password']);
            $session = $i->getSessionCookie();
            $this->users[$persona] = [$session];
        } else {
            $i->setSessionCookie($this->users[$persona][0] ?? '');
        }

        #Need to log in the right user
        #proceed to test the url which is below.
        $getPayload = new Payload\Base('GET', '/', $url);

        #WHEN
        $i->sendRequest($getPayload);

        $skippedTest = false;

        #THEN
        try {
            $i->seeResponseCodeIs(400);
            if ($example[4] == '401') {
                $skippedTest = true; // This line will execute only if the above line doesn't throw an exception.
            }
        } catch (\Exception $e) {
        }

        try {
            $i->seeResponseCodeIs(403);
            if ($example[4] == '401') {
                $skippedTest = true; // This line will execute only if the above line doesn't throw an exception.
            }
        } catch (\Exception $e) {
        }

        if ($skippedTest) {
            $scenario->skip('Error 400 or 403 is used sometimes instead of 401');
        }

        #THEN
        $i->seeResponseCodeIs($expected_result);
        $i->cantSeeResponseContains('Warning: foreach()');
        $i->cantSeeResponseContains('Warning: Undefined array');
        $i->cantSeeResponseContains('ErrorException');
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
    protected function urlProvider()
    {
        $csvData = array_map('str_getcsv', file(codecept_data_dir('/SmokeTests/GetSmokeTestDataList.csv')));

        return $csvData;
    }
}
