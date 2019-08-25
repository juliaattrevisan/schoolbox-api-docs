<?php


namespace SmokeTests;


use Codeception\Exception\ConditionalAssertionFailed;
use IntegrationTester;
use Payload\GetPage;

class FetchPagesFromCsvCest
{
    public function _before(IntegrationTester $i)
    {

        $i->loginAdmin(); #we logged in as alaress
    }

    /** Get all the pages, uses the url provider, data that is read from a csv to fetch all the pages stated and
     * checks that all pages come back with a 200 ok response.
     *
     * @dataProvider urlProvider
     */
    public function getAllThePages(IntegrationTester $i, \Codeception\Example $example)
    {
        $url=$example[0];

        $getPayload = new GetPage();
        $getPayload->setURL($url);
        $response = $i->sendCurlRequest($getPayload, true, 'fetch - '.$url);
        $i->assertEquals(200, $response->getStatusCode());
    }

    /** Url Provider - reads a csv file to provide an array of data for the fetch all pages test.
     *
     * @return array
     */
    protected function urlProvider()
    {
        $csvData = array_map('str_getcsv', file('test/codeception/integration/SmokeTests/data/FetchPagesFromCsvCest.csv'));
        return $csvData;
    }
}
