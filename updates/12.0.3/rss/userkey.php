<?php
/**
 * User Key checking and creation.
 * Relates to SB-1211
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

require_once(dirname(__FILE__) . '/../../../include/Bootstrap.php');
$db = new DeprecatedDatabase();

$sql = "SELECT `id` FROM `user` WHERE `key` IS NULL";
$users = $db->getAll($sql);

if(!count($users) || !is_array($users)) {
    die("No null users.\n");
}

// collate the query internals
foreach($users as $user) {
    $whens[$user['id']] = " WHEN `id` = " . $user['id'] . " THEN '" . substr(md5(uniqid() . time()), 0, 25) ."'";
}

if(!isset($whens)) {
    die("No null users.\n");
}

$ids = implode(',', array_keys($whens));
$whens = implode("\n", $whens);

// preare the uberstatement
$statement =
    "UPDATE `user`
        SET `key` = CASE
            $whens
        END
    WHERE `id` IN ($ids)";

 echo $statement;

 $db->query($statement);

?>