<?php

use Schoolbox\Learning\Learnosity\Api;

/** @var SitePage $this */

$configLoader = \Schoolbox\Config\ConfigMapper::getInstance()->getConfigLoader();

$api = new Api(
    (new \Schoolbox\Url\Factory($configLoader))->buildFullyQualified()->getHost(),
    '2023.3.LTS',
    '*****',
    '******'
);
//
$learnosityJobResponse = $api->dataGetRequest(
    '/jobs',
    ["references" => ['1a9eb9ea-6720-4ca5-87c9-30a94b94d586']], // learnosity_job_reference
);
//
//
new \Alaress_Probe($learnosityJobResponse);

////
/// broken:|         22007 | 🌐 What is Bluetooth?                                      | 983cc2a6-e506-4654-b2fc-d643d21ec507 |
/// working|         17406 | 🌐 What is Bluetooth?                                      | 6746ea38-561e-4466-b7da-ed5e0d52fe52 |
$itemReferences = $api->dataGetRequest('/itembank/activities', [
    'references' => [
    '33884062-411b-4726-8d17-ed3b7f6955c3', // learnosity_reference
    ]
]);

new \Alaress_Probe($itemReferences);

//
//$items = $api->dataGetRequest('/itembank/items', ['references' => ['983cc2a6-e506-4654-b2fc-d643d21ec507']]);
//
//new \Alaress_Probe($items);

/**
 * you probably want to start with GetActivities to see if you can load that.
 *
 * all the api refs are indexed in the epic somewhere, but here's a natural starting point: https://reference.learnosity.com/data-api/endpoints/itembank_endpoints#getActivities
 *
 * you can google for error codes, but this is mostly useless except for trivial mistakes you can easily find with your eyeballs: https://reference.learnosity.com/items-api/troubleshooting
 *
 * there are many error codes that aren't listed here
 *
 * and numerous error codes are caused by unrelated reasons
 *
 * anyway from Get Activities, you'll be given a list of Item ids, which you can then track with Get Items
 *
 * and inside each item is either a Question or a Feature, which unsurprisingly you can investigate with their corresponding endpoints
 *
 * one of them will probably die, but why...that's no longer really a question i can answer as learnosity holds all the data now.
 *
 * most of the time i end up in their zendesk, get flubbed off, then ignored
 *
 * the questions we're asking are beyond the ken of most of their zendesk now
 */
