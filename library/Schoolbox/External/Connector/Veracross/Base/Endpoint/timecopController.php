<?php

//require_once __DIR__ . '/../conf/core/require.php';
//
//$config = new \Schoolbox\Config\Loader();
//
//$host = $config->get('schooldata_import_endpoint_host');
//$dsn = $config->get('schooldata_import_endpoint_key');
//
//use Schoolbox\External\Connector\Veracross\Base\Endpoint;
//use Schoolbox\External\Connector\Veracross\Base\Endpoint\Lookup\EmergencyContact;
//use Schoolbox\External\Connector\Veracross\Base\Endpoint\Lookup\Health;
//use Schoolbox\External\Connector\Veracross\Base\Endpoint\Lookup\Household;
//use Schoolbox\External\Connector\Veracross\Base\Endpoint\Lookup\Language;
//use Schoolbox\External\Connector\Veracross\Base\Endpoint\Lookup\Relationship;
//use Schoolbox\External\Connector\Veracross\Base\Endpoint\Lookup\ValueListConverter;
//
//define('DEFAULT_TIMEOUT', 300);
//$defaultTimeout = 300;
//
///* JCVD this stuff
// */
//$timecop = new Endpoint\Timecop();
//$timecop->regulate('start');
//
//$api = new Endpoint\Api($host, $dsn, DEFAULT_TIMEOUT, $timecop);
//
///* Get ready to remember an indexed set of Value Lists for Students
// */
//$studentLookupConverter = new ValueListConverter();
//
///* Spawn a Generator of paginated payloads
// */
//$timecop->regulate('students endpoint: Setting up studentPage generator');
//$studentPayloadGenerator = $api->retrieve('GET', 'students', Endpoint\Profile\Profile::SCOPE);
//$timecop->regulate('students endpoint: studentPage generator ready');
//
///* Manufacture lookups, for performance
// */
//$lookupConverter = (new Endpoint\Lookup\ValueListConverter());
//
//$medicalLookup = (new Health($api))->generate();
//$timecop->regulate('Prefetching medicals');
//
//$medicalLookup = (new Language($api))->generate();
//$timecop->regulate('Prefetching languages');
//
//$householdLookup = (new Household($api))->generate();
//$timecop->regulate('Prefetching households');
//
//$relationshipLookup = (new Relationship($api))->generate();
//$timecop->regulate('Prefetching relationships');
//
//$emergencyContactsLookup = (new EmergencyContact($api))->generate();
//$timecop->regulate('Prefetching emergency contacts');
//
//$contactInfoLookup = (new Endpoint\Lookup\ContactInfo($api))->generate();
//$timecop->regulate('Prefetching contact info');
//
///* Crunch through the payload data
// *
// * Process 10 students
// */
//$i = 0;
//$n = 10;
//
//foreach ($studentPayloadGenerator as $studentPage) {
//    $timecop->regulate('studentPage fetched from endpoint');
//    $timecop->regulate('Proceeding through pagination');
//
//    $timecop->regulate('    Start converting Value Lists');
//    $studentLookups = $lookupConverter->generate($studentPage['value_lists']);
//    $timecop->regulate('    Finished converting Value Lists');
//
//    $timecop->regulate('    Proceeding through each student record in the page');
//    foreach ($studentPage['data'] as $student) {
//        $timecop->regulate('        $student fetched');
//
//        $timecop->regulate('        parents endpoint: Setting up parentPage generator for this houshold_id');
//        $parentPayloadGenerator = $api->retrieve('GET', 'parents', Endpoint\Profile\Profile::SCOPE, [
//            'household_id' => $student['household_id']
//        ]);
//        $timecop->regulate('        parents endpoint: parentPage generator ready');
//
//        foreach ($parentPayloadGenerator as $parentPage) {
//            $timecop->regulate('            parentPage fetched from endpoint');
//            $timecop->regulate('            Start converting Value Lists');
//            $parentLookups = $lookupConverter->generate($parentPage['value_lists']);
//            $timecop->regulate('            Finished converting Value Lists');
//
//            $timecop->regulate('            Proceeding through each parent record in the page');
//            foreach ($parentPage['data'] as $parent) {
//                // noop
//            }
//            $timecop->regulate('            parentPage traversed');
//        }
//        $timecop->regulate('        studentPage traversed');
//
//        /* We processed a guy!
//         */
//        $i++;
//
//        /* FIXME Forcibly exit after first page of 10 students
//         */
//        if ($i >= $n) {
//            $timecop->regulate('end');
//
//            $timecop->generateTimeline();
//            die();
//        }
//    }
//}
//
//die();
