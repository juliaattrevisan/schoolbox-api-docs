<?php
/**
 * Created by JetBrains PhpStorm.
 * User: aetworowsky
 * Date: 17/03/11
 * Time: 10:30 AM
 * To change this template use File | Settings | File Templates.
 */

$string = $_REQUEST['string'];
$format = $_REQUEST['format'];

if (empty($format)) {
    $format = 'd/m/Y';
}

//$datetime = DateTime::createFromFormat($format, $string);



if (!$datetime) {
    $datetime = new DateTime( str_replace('/','-',$string));
}

echo json_encode(array( 'time' => $datetime->format($format) ));

?>