<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

// this is a script and not to be SitePage'd
if(isset($this)) {
    error(4);
}
require_once('../../include/Bootstrap.php');

if(!(is_array($_POST['crits']) && count($_POST['crits']) && is_numeric($_POST['categoryID']))) {
    error(4);
}

$criteria = $_POST['crits'];
$catID = $_POST['categoryID'];

// this merely re-establishes the session
new SitePage();

// "security to the foyer!"
$groupHandler = new Group();
if(!($groupHandler->isSuperuser() || $groupHandler->getPermission($catID) >= WRITE_ACCESS)) {
    die('You to not have write permission to this category.');
}

$data = array();

foreach($criteria as $group) {
    if(count($group['items'])) {
        foreach($group['items'] as $critID => $crit) {
            $critID = substr($critID, 1);
            $data[$critID] = array(
                'group' => $group['name'],
                'name'  => $crit,
            );
        }
    }
}


// get pre-existing criteria details
$rubricHandler = new DueWork_Rubric_Handler();
$dbcriteria = $rubricHandler->getAvailableCriteria($catID, 'category', true);

// create a list of criteria that we cannot permit changes to
$locked = array();
$existing = array();
if(count($dbcriteria)) {
    foreach($dbcriteria as $groupname => $groupitems) {
        foreach($groupitems as $id => $details) {
            if($details['count']) {
                $locked[] = $id;
            }
            $existing[] = $id;
        }
    }
}

// to be refactored
$db = Database::getInstance();

$stmt['insert'] = $db->prepare("
    INSERT INTO rubric_criterion
      (`grouping`, `name`, `parent_id`)
    VALUES
      (:group, :name, :pid)
");
$stmt['update'] = $db->prepare("
    UPDATE rubric_criterion
    SET
      `grouping` = :group,
      `name` = :name
    WHERE id = :cid
");
$stmt['delete'] = $db->prepare("
    DELETE FROM rubric_criterion
    WHERE id = :cid
");


// iterate through list of provided criteria changes
foreach($data as $critID => $critData) {

    // don't edit locked criteria
    if(in_array($critID, $locked)) {
        continue; // possibly throw an error of some kind
    }

    if(in_array($critID, $existing)) {
        // perform update
        $exec = array_merge($critData, array('cid' => $critID));
        $method = 'update';
    }
    else {
        // perform insert
        $exec = array_merge($critData, array('pid' => $catID));
        $method = 'insert';
    }

    $stmt[$method]->execute($exec);

}

// finally, work out the diff and delete unnecessary criteria
$found = array_keys($data);
foreach($existing as $eID) {
    if(!(in_array($eID, $found) || in_array($eID, $locked))) {
        $stmt['delete']->execute(array('cid' => $eID));
    }
}

die('Criteria updated.');