<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */


use Schoolbox\Assessment\Component\SubmissionBox\Rubric\Criterion;
use Schoolbox\Assessment\Component\SubmissionBox\Rubric\CriterionMapper;
use Schoolbox\Assessment\Component\SubmissionBox\Rubric\RubricMapper;
use Schoolbox\Assessment\Component\SubmissionBox\Rubric\UsageMapper;
use Schoolbox\Assessment\Component\SubmissionBoxMapper;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Error;

require_once __DIR__ . '/../../library/Bootstrap.php';

// this is a script and not to be SitePage'd
if (isset($this)) {
    error(Error\Mapper::ERROR_EMAIL_NOT_RETRIEVED);
}

if (!(is_array($_POST['crits']) && count($_POST['crits']) && is_numeric($_POST['categoryID']))) {
    error(Error\Mapper::ERROR_EMAIL_NOT_RETRIEVED);
}

$criteria = $_POST['crits'];
unset ($criteria[-1]);

$folderId = $_POST['categoryID'];

// this merely re-establishes the session
$page = new SitePage();

$userAccess = new Access();
if (!($_SESSION['user']->isSuperuser() || $userAccess->getFolderAccess($_SESSION['user'], $folderId) >= UserAccess::ACCESS_WRITE)) {
    error(Error\Mapper::ERROR_PERM_CATEGORY_WRITE);
}

// get category details
$folder = FolderMapper::getInstance()->findById($folderId);

$data = [];

foreach ($criteria as $group) {
    if (count($group['items'])) {
        foreach ($group['items'] as $critID => $crit) {
            $critID = substr($critID, 1);
            $data[$critID] = [
                'group' => $group['name'],
                'name'  => $crit,
            ];
        }
    }
}

/* @var $criterionMapper CriterionMapper */
$criterionMapper = CriterionMapper::getInstance();

// Which submission boxes have criteria deleted? We will need to ensure we don't leave in an invalid state.
$affectedSubmissionBoxes = SubmissionBoxMapper::getInstance()->createCollection();

//work out the diff and delete unnecessary criteria
$found = array_keys($data);

$criterionCollection = $criterionMapper->findByColumnList(
    [
        'parent_id'   => $folderId,
        'parent_type' => Criterion::PARENT_TYPE_CATEGORY,
    ]
);

foreach ($criterionCollection as $crit) {
    if (!in_array($crit->getID(), $found)) {
        try {
            $usages = UsageMapper::getInstance()->findByColumn('criterion_id', $crit->getId());
            $criterionMapper->delete($crit);
            foreach($usages as $usage){
                $box = $usage->getSubmissionBox();
                $affectedSubmissionBoxes[$box->getId()] = $box;
            }
        } catch (Exception $e) {
            die('Criteria not found');
        }
    }
}
// Ensure that deletion of a criterion, and its descriptors, does not result in empty levels.
RubricMapper::getInstance()->deleteEmptyLevels($affectedSubmissionBoxes->keys());


// iterate through list of provided criteria changes
foreach ($data as $critID => $critData) {
    try {
        $criteria = $criterionMapper->findOneByColumnList(
            [
                'id'        => $critID,
                'parent_id' => $folderId,
            ]
        );
        $criteria->setGrouping($critData['group']);
        $criteria->setName($critData['name']);

        //Do not edit criteria that are in use on a class page, can edit in course though
        $usage = UsageMapper::getInstance()->findByColumnList(['criterion_id' => $critID]);
        if (count($usage) && !($folder->hasCourseSpecification() || $folder->hasUnitSpecification())) {
            continue; // possibly throw an error of some kind
        }
    } catch (Exception $e) {
        $criteria = new Criterion();
        $criteria->setParentId($folderId);
        $criteria->setGrouping($critData['group']);
        $criteria->setName($critData['name']);
    }

    $criterionMapper->save($criteria);
}


if ($page::detectAjax()) {
    die('Criteria updated.');
}
$page::localRedir('/homepage/' . $folderId);
