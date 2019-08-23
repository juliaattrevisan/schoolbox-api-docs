<?php


namespace SmokeTests;


use IntegrationTester;
use Payload\GetPage;

class FetchPagesFromCsvCest
{
    public $csvData;
    public function _before(IntegrationTester $i)
    {
        $this->csvData = array_map('str_getcsv', file('test/codeception/integration/SmokeTests/data/FetchPagesFromCsvCest.csv'));
        $i->loginAdmin(); #we logged in as alaress
    }

    public function getAllThePages(IntegrationTester $i, GetPage $getPayload)
    {
        foreach($this->csvData as $oneEntry)
        {
            $url=$oneEntry[0];
            #Set up a GET payload.
            $getPayload->setURL($url);
            #send payload.
            $response = $i->sendCurlRequest($getPayload, true, 'fetch - '.$url);
            #assert return is 200 OK.
            $i->assertEquals(200, $response->getStatusCode());
        }
    }
}
