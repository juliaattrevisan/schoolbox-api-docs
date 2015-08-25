<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */


use Schoolbox\Assessment\Component\SubmissionBox\Rubric\CriterionMapper;
use Schoolbox\Assessment\Component\SubmissionBox\Rubric\Criterion;
use Schoolbox\Assessment\Component\SubmissionBox\Rubric\UsageMapper;


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
$page = new SitePage();

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

$criterionMapper = CriterionMapper::getInstance();



//work out the diff and delete unnecessary criteria
$found = array_keys($data);

$criterionCollection = $criterionMapper->findByColumnList(
                                       array(
                                           'parent_id'   => $catID,
                                           'parent_type' => Criterion::PARENT_TYPE_CATEGORY,
                                       )
);

foreach($criterionCollection as $crit) {
    if(!in_array($crit->getID(), $found)) {
        try {
            $criterionMapper->delete($crit);
        } catch (Exception $e) {
            die('Criteria not found');
        }
    }
}


/* @var $criterionMapper CriteriaMapper */

// iterate through list of provided criteria changes
foreach($data as $critID => $critData) {
    try {
        $criteria = $criterionMapper->findOneByColumnList(
            array(
                'id'        => $critID,
                'parent_id' => $catID,
            )
        );
        $criteria->setGrouping($critData['group']);
        $criteria->setName($critData['name']);

        //Do not edit criteria that are in use
        $usage = UsageMapper::getInstance()->findByColumnList(array('criterion_id' => $critID));
        if(count($usage)) {
            continue; // possibly throw an error of some kind
        }
    }
    catch(Exception $e) {
        $criteria = new Criterion();
        $criteria->setParentId($catID);
        $criteria->setGrouping($critData['group']);
        $criteria->setName($critData['name']);
    }

    $criterionMapper->save($criteria);
}


if ($page->detectAjax()) {
    die('Criteria updated.');
} else {
    $page->backToTheFuture(2);
}
