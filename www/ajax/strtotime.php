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


$string = isset($_REQUEST['string']) ? $_REQUEST['string'] : '';
$format = isset($_REQUEST['format']) ? $_REQUEST['format'] : '';
$full   = $format === 'local_full';

if (empty($format) || $full) {
    // for performance, only include the boostrap if you *absolutely* must
    require_once __DIR__ . '/../../library/Bootstrap.php';

    $format = Config::get('date_local');

    // if we want a full datetime as localised, append the time
    if ($full) {
        $format .= ' ' . Config::get('date_time');
    }
}



////
////  WARNING: no library code beyond this point - bootstrap is not guaranteed  ////
////


if (empty($string)) {
    // null is ok: failfast
    die(json_encode([ 'time' => null ]));
}

// compensate for natural language
$parseable = denaturalise($string);

$datetime = DateTime::createFromFormat($format, $parseable);

if (!$datetime) {
    try {
        $datetime = new DateTime(str_replace('/','-', $parseable));

    } catch (Exception $ex) {
        // noop
    }
}

if ($datetime instanceof DateTime) {
    die(json_encode([ 'time' => $datetime->format($format) ]));
}

// Datetime wasn't able to be created? error out but preserve old error
// functionality (where time doesn't change at all)
die(json_encode([
    'time'  => $string,
    'error' => 'Could not parse date',
]));


/**
 * Attempt to normalise natural language into what strtotime() will be able to understand
 *
 * @param string $string
 * @return string
 */
function denaturalise($string)
{

    // normalise any whitespace
    $string = preg_replace('`\s+`', ' ', trim($string));

    // remove "^in " and " from now$"
    $string = str_replace(['in ', ' from now', ' at '], ' ', $string);

    // replace all word-numbers with number-numbers
    $words = [
        'zero', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine', 'ten', 'eleven', 'twelve'
    ];

    $string = str_replace($words, array_keys($words), $string);

    // known limitation: 23:00am fails to parse

    return $string;
}