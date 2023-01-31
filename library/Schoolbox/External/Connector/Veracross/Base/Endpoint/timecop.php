<?php

require_once __DIR__ . '/../conf/core/require.php';

$config = new \Schoolbox\Config\Loader();

$host = $config->get('schooldata_import_endpoint_host');
$dsn = $config->get('schooldata_import_endpoint_key');

use Schoolbox\External\Connector\Veracross\Base\Endpoint;

$api = new Endpoint\Api($host, $dsn, 'students:list parents:list');

$timecop = [];
$start = microtime(true);

/* Get ready to consume all the pages from the "students" endpoint,
 * using the scope "students:list"
 */
$studentPayloadGenerator = $api->retrieve('GET', 'students');

/* Rebuild lookups on each page (see TODO)
 */
$lookupConverter = (new Endpoint\ValueListConverter());

$timecop[] = [$start, 'start'];

foreach ($studentPayloadGenerator as $studentPage) {
    $timecop[] = [microtime(true), 'studentPage fetched'];
    $studentLookups = $lookupConverter->generate($studentPage['value_lists']);

    foreach ($studentPage['data'] as $student) {
        $parentPayloadGenerator = $api->retrieve('GET', 'parents', [
            'household_id' => $student['household_id']
        ]);

        $timecop[] = [microtime(true), 'about to retrieve from parentPage generator'];
        foreach ($parentPayloadGenerator as $parentPage) {
            $timecop[] = [microtime(true), 'parentPage fetched'];
            $parentLookups = $lookupConverter->generate($parentPage['value_lists']);

            foreach ($parentPage['data'] as $parent) {
//                new \Alaress_Probe(
//                    $student ?? null,
//                    $parent ?? null,
//                    $studentLookups ?? null,
//                    $parentLookups ?? null
//                );
            }
            $timecop[] = [microtime(true), 'parentPage traversed'];
        }
        $timecop[] = [microtime(true), 'studentPage traversed'];
    }
}

$end = microtime(true);
$timecop[] = [$end, 'end'];

$previousTimestamp = null;
$diff = null;

new \Alaress_Probe('id', 'event', 'diff from previous', 'time');

foreach ($timecop as $i => $row) {
    if ($previousTimestamp) {
        $diff = $row[0] - $previousTimestamp;
    }

    new \Alaress_Probe($i, $row[1], number_format($diff, 3), $row[0]);

    $previousTimestamp = $row[0];
}

new \Alaress_Probe('<strong>Total</strong>: ' . number_format(($end - $start), 3));

die();
