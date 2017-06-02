<?php
/**
 * Created by JetBrains PhpStorm.
 * User: aetworowsky
 * Date: 17/03/11
 * Time: 10:30 AM
 * To change this template use File | Settings | File Templates.
 */

// always JSON response
header('Content-Type: application/json');

$naturalLanguage = isset($_REQUEST['string']) ? $_REQUEST['string'] : '';

if (empty($naturalLanguage)) {
    // null is ok: failfast
    die(json_encode([ 'time' => null ]));
}

require_once __DIR__ . '/../../library/Bootstrap.php';

try {
    $adf = Alaress_Date_Format::newNaturalDate($naturalLanguage);

} catch (InvalidArgumentException $e) {
    // Datetime wasn't able to be created? error out but preserve old error
    // functionality (where time doesn't change at all)
    die(json_encode([
        'time'  => $naturalLanguage,
        'error' => 'Could not parse date',
    ]));
}

die(json_encode([ 'time' => $adf->toIso8601String() ]));
