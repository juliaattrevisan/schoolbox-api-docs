<?php
/**
 * Created by JetBrains PhpStorm.
 * User: aetworowsky
 * Date: 17/03/11
 * Time: 10:30 AM
 * To change this template use File | Settings | File Templates.
 */

require_once __DIR__ . '/../../include/Bootstrap.php';

$string = $_REQUEST['string'];
$format = $_REQUEST['format'];

if (empty($format)) {
    $format = Config::get('date_local');
}

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
