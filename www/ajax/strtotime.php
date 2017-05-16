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


if (empty($format) || $format === 'local_full') {
    // for performance, only include the boostrap if you *absolutely* must
    require_once __DIR__ . '/../../library/Bootstrap.php';

    $format = Config::get('date_local');

    // if we want a full datetime as localised, append the time
    if ($format === 'local_full') {
        $format .= ' ' . Config::get('date_time');
    }
}



////
////  WARNING: no library code beyond this point - bootstrap is not guaranteed  ////
////


/* Allow null values
 */
if (empty($string)) {
	echo json_encode(array(
		'time' => null,
	));
}
else {
	$datetime = DateTime::createFromFormat($format, $string);

	if (!$datetime) {
		try {
			$datetime = new DateTime(str_replace('/','-',$string));
		}
		catch (Exception $ex) {}
	}

	if ($datetime instanceof DateTime) {
		echo json_encode(
			array(
				'time' => $datetime->format($format)
			)
		);
	}
	else {
		//
		// Datetime wasn't able to be created? error out but preserve old error
		// functionality (where time doesn't change at all)
		echo json_encode(
			array(
				'time'  => $string,
				'error' => 'Could not parse date',
			)
		);
	}
}
