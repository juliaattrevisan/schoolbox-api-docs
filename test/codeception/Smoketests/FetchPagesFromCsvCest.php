<?php

namespace Tests\Smoketests;

use Schoolbox\Config\Loader;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Url\Factory;
use Schoolbox\Url\Uri;
use Tests\Support\IntegrationTester;
use Tests\Support\Payload;
use Codeception\Scenario;
use SensitiveParameter;

// the csv files are located under Support/Data/SmokeTests - add the new URL in the GetSmokeTestDataList.csv
class FetchPagesFromCsvCest
{
    #Need some constants to represent users or logic in the before that sets the users.
    protected $personas = [
        'Superuser'              => ['username' => 'alaress', 'password' => 'alaress'],
        'Admin'                  => ['username' => 'alaress', 'password' => 'alaress'],
        'Teacher'                => ['username' => 'achan', 'password' => 'password'],
        'Student'                => ['username' => 'jreed', 'password' => 'password'],
        'Parent'                 => ['username' => 'jennireed01', 'password' => 'password'],
        'Fixtures Administrator' => ['username' => 'jcoles', 'password' => 'password'],
        'Badges Moderator'       => ['username' => 'staff10', 'password' => 'password'], // -+- Permissions for these
        'CMS Moderator'          => ['username' => 'staff11', 'password' => 'password'], //  |  are set up in the
        'Calendar Moderator'     => ['username' => 'staff12', 'password' => 'password'], //  |  _before() function.
        'Design Moderator'       => ['username' => 'staff13', 'password' => 'password'], //  |
        'LMS Moderator'          => ['username' => 'staff14', 'password' => 'password'], //  |
        'News Moderator'         => ['username' => 'staff15', 'password' => 'password'], //  |
        'Pastoral Moderator'     => ['username' => 'staff16', 'password' => 'password'], //  |
        'Timetable Moderator'    => ['username' => 'staff17', 'password' => 'password'], // /
    ];

    protected $users = [];

    /**
     * Set up service permissions for moderator personas.
     * @param IntegrationTester $i
     */
    public function _before(IntegrationTester $i): void
    {
        $serviceIdByPersonaName = [
            'Badges Moderator'    => UserAccess::SERVICE_BADGE,
            'CMS Moderator'       => UserAccess::SERVICE_CMS,
            'Calendar Moderator'  => UserAccess::SERVICE_CALENDAR,
            'Design Moderator'    => UserAccess::SERVICE_DESIGN,
            'LMS Moderator'       => UserAccess::SERVICE_LMS,
            'News Moderator'      => UserAccess::SERVICE_NEWS,
            'Pastoral Moderator'  => UserAccess::SERVICE_PASTORAL,
            'Timetable Moderator' => UserAccess::SERVICE_TIMETABLE,
        ];

        foreach ($serviceIdByPersonaName as $personaName => $serviceId) {
            // Grant full service permission to persona.
            $i->haveInDatabase(
                'userAccess',
                [
                    'uid' => $i->grabFromDatabase('user', 'id', ['username' => $this->personas[$personaName]['username']]),
                    'sid' => ($personaName === 'Calendar Moderator') ? 1 : 0, // The calendar service requires sid to link to luCampus.id.
                    'service' => $serviceId,
                    'type' => UserAccess::ACCESS_FULL,
                    'created_at' => date('Y-m-d H:i:s'),
                ]
            );
        }
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
     * @param Scenario          $scenario
     * @throws \JsonException
     * @throws \Exception
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

        #THEN
        $this->seeResponseCodeIsOneOf($i, [401, 403]);
    }

    /**
     * Fail test to ensure we can get a permission denied from schoolbox using Aura
     *
     * @param IntegrationTester $i
     * @throws \Exception
     */
    public function getFailureAuraPermissionDenied(IntegrationTester $i, Scenario $scenario): void
    {
        $page                 = '/adminv2/setting';
        $student_user_details = $this->personas['Student'];

        #WHEN
        $this->loginUser($i, $student_user_details['username'], $student_user_details['password']);

        $getPayload = new Payload\Base('GET', '/', $page);
        $i->sendRequest($getPayload);

        #THEN
        // Note '403 Forbidden' is used sometimes instead of '401 Unauthorized'.
        $this->seeResponseCodeIsOneOf($i, [401, 403]);
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
     * @throws \Exception
     */
    public function getAllThePages(IntegrationTester $i, Scenario $scenario, \Codeception\Example $example): void
    {
        # TODO split out tests into one per persona, each test sends a parameter to the url provider which determines the dataset.
        #GIVEN
        $persona      = $example[2];
        $url          = $example[3];
        $expected_result = $example[4];

        // For the URL: override the host if it's vagrant.dev.schoolbox.cloud
        $baseUrl   = new Uri($i->grabBaseUrl());
        $parsedUrl = new Uri($url);
        if ($parsedUrl->getHost() === 'vagrant.dev.schoolbox.cloud') {
            $parsedUrl = $parsedUrl->withHost($baseUrl->getHost());
            $url       = $parsedUrl->__toString();
        }

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

        #THEN
        $this->seeResponseCodeIsOneOf(
            $i,
            // Note '403 Forbidden' is used sometimes instead of '401 Unauthorized'.
            // '400 Bad Request' is the default generated by Error\Mapper->generateException().
            // E.g. Code like `throw Error\Mapper->generateException(ErrorMapper::ERROR_PERM_PAGE_VIEW);`
            //      will result in a 400 HTTP status code.  This pattern is widespread.
            in_array($expected_result, [401, 403]) ? [400, 401, 403] : [$expected_result]
        );
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
        $csvData = array_map(
            fn ($line) => str_getcsv($line, escape: ''),
            file(codecept_data_dir('/SmokeTests/GetSmokeTestDataList.csv')),
        );

        return $csvData;
    }

    /**
     * This function was added because as a result of changes made during the Granular Permissions epic, some tests
     * expecting 401 started returning 403.  To update the expected code for each affected test (in the CSV file) would
     * take too long, as would modifying the permissions code to consistently return the same code.  So instead, tests
     * expecting a particular code were modified to allow a set of acceptable substitute codes.
     *
     * @param IntegrationTester $i
     * @param array<int> $expectedCodes
     * @throws \Exception
     */
    private function seeResponseCodeIsOneOf(IntegrationTester $i, array $expectedCodes): void
    {
        foreach ($expectedCodes as $expectedCode) {
            if ($this->responseCodeIs($i, $expectedCode)) {
                return;
            }
        }

        throw new \Exception(
            'HTTP Response code ' . $i->grabResponseCode() .
            ' is not one of: {' . implode(', ', $expectedCodes) . '}.'
        );
    }

    /**
     * @param IntegrationTester $i
     * @param int               $expectedCode
     * @return bool
     */
    private function responseCodeIs(IntegrationTester $i, int $expectedCode): bool
    {
        try {
            $i->seeResponseCodeIs($expectedCode);
        } catch (\Exception $e) {
            return false;
        }

        return true;
    }
}
