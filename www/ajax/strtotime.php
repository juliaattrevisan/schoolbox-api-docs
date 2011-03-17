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

$datetime = new DateTime($string);
$output = array(
    'time' => $datetime->format($format)
);

echo json_encode($output);

?>