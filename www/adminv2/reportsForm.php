<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

require_once(dirname(__FILE__) . '/../../library/Bootstrap.php');

if(!$_POST['bounce']) {
    error(2);
}

// parse their filter inputs and redirect back to the page
$bounce = "Location: /adminv2/reports/" . $_POST['bounce'] . "/";
$vars = "";

foreach($_POST as $post => $val) {
    if($post == 'bounce')
        continue;
    if($val = str_replace('/','-',$val))
        $vars .= "$post=$val,";
}

$vars = substr($vars, 0, strlen($vars) - 1);

if($vars == '')
    $vars = 'all';

header($bounce . $vars);

?>
