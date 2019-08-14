<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

use Codeception\TestInterface;
use Payload\Login as LoginPayload;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Response;
use const GuzzleHelper\VAGRANT_DOMAIN;

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

    use GuzzleHelper\GuzzleActions;

    use SchoolboxHelper\SchoolboxActions;

    /**
     * checkCurlReturn checks the return values against the actual values that have been returned during the test.
     *
     * @param array $expectedReturn  #array[[$URL => $STATUS_CODE]]
     * @param \GuzzleHttp\Psr7\Response $response
     */
    public function checkCurlReturn(array $expectedReturn, \GuzzleHttp\Psr7\Response $response):void
    {
        $redirectHistoryArray = $this->prepareGuzzleRedirectHistoryToArray($response);

        if (count($expectedReturn) == count($redirectHistoryArray))
        {
            $numberOfReturnUrls = 0;
            while ($numberOfReturnUrls < count($redirectHistoryArray))
            {
                if (!array_key_exists('-', $expectedReturn[$numberOfReturnUrls]))
                {
                    $this->assertEquals($expectedReturn[$numberOfReturnUrls], $redirectHistoryArray[$numberOfReturnUrls], 'Expect a redirect');
                } else {
                    $this->assertEquals($expectedReturn[$numberOfReturnUrls], $redirectHistoryArray[$numberOfReturnUrls], 'Expect last return code');
                }

                $numberOfReturnUrls++;
            }
        } else
        {

            $this->assertEquals(count($expectedReturn), count($redirectHistoryArray), 'Action path of request has less steps then the expected amount of steps. '.print_r($expectedReturn).' compared to '.print_r($redirectHistoryArray));
        }
    }

    /** @var CookieJar $client_cookie_jar */
    public $client_cookie_jar;

    public $guzzle_headers=[];

    public $redirect_settings;

    public function _before(TestInterface $test)
    {
        $this->initGuzzleClient2();
    }

    public function initGuzzleClient2()
    {
        if (!isset($this->redirect_settings)) {
            $this->guzzle_headers = [
                'Host'         => VAGRANT_DOMAIN,
                'Content-Type' => 'application/x-www-form-urlencoded; charset=UTF-8',
                'Accept'       => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8'
            ];
            $this->redirect_settings = [
                'max'             => 5,
                'referer'         => true,
                'protocols'       => ['http', 'https'],
                'track_redirects' => true
            ];
            $this->client_cookie_jar = new CookieJar();
        }
    }

    /**
     * loginUser fire off Curl to login user.
     *
     * @param \Payload\Base $requestDetails  request details for the curl request.
     * @param bool $redirect if the request should follow the redirect.
     *
     * @return Response
     */
    public function sendCurlRequest( \Payload\Base $requestDetails, bool $redirect, string $step_message=''):Response
    {
        $this->initGuzzleClient2();

        $requestDetailsArray = $requestDetails->payloadToArray();

        $method = $requestDetailsArray['method'];
        $page = $requestDetailsArray['page'];
        $params = $requestDetailsArray['payload'];

        if ($redirect) {
            $params['allow_redirects'] = $this->redirect_settings;
        } else {
            $params['allow_redirects'] = false;
        }

        $params['cookies'] = $this->client_cookie_jar;

        if ($step_message != '')
        {
            $this->amGoingTo($step_message);
        }

        return $this->sendRequestCurl($method, $page, $params);
    }


    public function loginAdmin2()
    {
        $loginPayload = new LoginPayload();
        $loginPayload->setUsername('alaress');
        $loginPayload->setPassword('alaress');

        $this->sendCurlRequest($loginPayload, false);
    }
}
