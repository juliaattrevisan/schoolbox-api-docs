<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

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

}
