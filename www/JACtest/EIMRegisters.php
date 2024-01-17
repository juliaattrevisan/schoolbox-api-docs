<?php
require_once __DIR__ . '/../../conf/core/require.php';
$kernel = new \Schoolbox\Application\WebRequest\Kernel();


// get config

use External\Isams\Register\CacheHelper;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Url\Uri;

$configMapper = ConfigMapper::getInstance();

$apiUrl = new Uri($configMapper->getConfigLoader()->get(ConfigMapper::EXTERNAL_ISAMS_REST_API_HOST));
$cache = new CacheHelper($kernel->get('httpClient'), $kernel->get('oidc.cache'), $configMapper->getConfigLoader(), $apiUrl);


//new \Alaress_Probe($this->cacheHelper->getRecordCollectionsRecords(7, '152840864613', 143));

//https://isams-dcsz.dulwich.org/Main/api/registers/ should give a list of registers.
//
//So for the register you want, use /registers/{registerId}/recordCollections to retrieve the ids of the record collections in use.
//
//Use that with /registers/{registerId}/students/{schoolId}/recordcollections/{recordCollectionId – ie from previous endpoint}/records


$registerCode = "MCTS";
$collectionCode = "CoreAssignmentData";
$studentExternalId = "152840864613";

$registers       = $cache->getRegisters();

foreach ($registers as $register) {
    if (strtolower($register['code']) !== strtolower($registerCode)) {
        // the requested register Ignoring...
        continue;
    }
    $validRegister = $register;
    break;
}

// we now have the correct register we are after
$recordCollections = $cache->getRecordCollections($register['id']);

foreach ($recordCollections as $collection) {
    if (strtolower($collection['reportingKey']) !== strtolower($collectionCode)) {
        // the requested register Ignoring...
        continue;
    }
    $recordCollection = $collection;
    break;
}

// we now have the correct record collection id

// fetch user data
$userRecords = $cache->getRecordCollectionsRecords($register['id'], $studentExternalId, $recordCollection['id']);

// fetch the field metadata to join and clean the data
$fieldMetadata = $cache->getMetadataFields($register['id']);

$out = [];
// loop through each record
foreach ($userRecords as $record) {
    $fields = [];
    //in each record, loop through the metadata fields
    foreach ($fieldMetadata as $metadata) {
        //if the field exists then build it, else just continue
        if (!isset($record['fields'][lcfirst($metadata['key'])])) {
            continue;
        }
        // join the record field to the metadata field and append to a new array
        $fields[$metadata['key']] = [
            'type' => $metadata['type'],
            'value' => $record['fields'][lcfirst($metadata['key'])]
        ];
    }
    $out[] = $fields;
}

new \Alaress_Probe($fieldMetadata, $validRegister, $recordCollection, $userRecords);

new \Alaress_Probe($out);
die();
