<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */


//use Schoolbox\Assessment\Component\SubmissionBox\Rubric;
//use Schoolbox\Assessment\Component\SubmissionBoxMapper;
//use Schoolbox\Resource\Folder\Mapper as FolderMapper;
//use Schoolbox\Permission\Access\Access;
//use Schoolbox\Permission\Access\UserAccess;
//use Schoolbox\Error;

require_once __DIR__ . '/../../conf/core/require.php';
$kernel = new \Schoolbox\Application\WebRequest\Kernel();

// This page no longer exists
SitePage::localRedir('/', null, 410);
exit;


//// this is a script and not to be SitePage'd
//if (isset($this)) {
//    error(Error\Mapper::ERROR_EMAIL_NOT_RETRIEVED);
//}
//
//if (!(is_array($_POST['crits']) && count($_POST['crits']) && is_numeric($_POST['categoryID']))) {
//    error(Error\Mapper::ERROR_EMAIL_NOT_RETRIEVED);
//}
//
//$criteria = $_POST['crits'];
//unset ($criteria[-1]);
//
//$folderId = $_POST['categoryID'];
//
//// this merely re-establishes the session
//$page = new SitePage();
//
//$userAccess = new Access();
//if (!($_SESSION['user']->isSuperuser() || $userAccess->getFolderAccess($_SESSION['user'], $folderId) >= UserAccess::ACCESS_WRITE)) {
//    error(Error\Mapper::ERROR_PERM_CATEGORY_WRITE);
//}
//
//// get category details
//$folder = FolderMapper::getInstance()->findById($folderId);
//
//$data = [];
//
//foreach ($criteria as $group) {
//    if (count($group['items'])) {
//        foreach ($group['items'] as $critID => $crit) {
//            $critID = substr($critID, 1);
//            $data[$critID] = [
//                'id'    => $group['id'],
//                'group' => $group['name'],
//                'name'  => $crit,
//            ];
//        }
//    }
//}
//
//$criterionMapper = Rubric\CriterionMapper::getInstance();
//$groupMapper     = Rubric\GroupMapper::getInstance();
//
//// Which submission boxes have criteria deleted? We will need to ensure we don't leave in an invalid state.
//$affectedSubmissionBoxes = SubmissionBoxMapper::getInstance()->createCollection();
//
////work out the diff and delete unnecessary criteria
//$found = array_keys($data);
//
//$criterionCollection = $criterionMapper->findForFolder($folderId);
//
//foreach ($criterionCollection as $crit) {
//    if (!in_array($crit->getID(), $found)) {
//        try {
//            $usages = Rubric\UsageMapper::getInstance()->findByColumn('criterion_id', $crit->getId());
//            $criterionMapper->delete($crit);
//            foreach($usages as $usage){
//                $box = $usage->getSubmissionBox();
//                $affectedSubmissionBoxes[$box->getId()] = $box;
//            }
//        } catch (Exception $e) {
//            die('Criteria not found');
//        }
//    }
//}
//// Ensure that deletion of a criterion, and its descriptors, does not result in empty levels.
//Rubric\RubricMapper::getInstance()->deleteEmptyLevels($affectedSubmissionBoxes->keys());
//
//
//// iterate through list of provided criteria changes
///** @var Rubric\Group[] $groupList */
//$groupList  = $groupMapper->createCollection();
//$parentType = Rubric\Group::PARENT_TYPE_CATEGORY;
//foreach ($data as $critID => $critData) {
//
//    // Find the group; if it doesn't exist create it
//    $groupName = $critData['group'];
//    if (!isset($groupList[$groupName])) {
//        if (isset($critData['id'])) {
//            try {
//                $group = $groupMapper->findOneByColumnList([
//                    'id'          => $critData['id'],
//                    'parent_id'   => $folderId,
//                    'parent_type' => $parentType,
//                ]);
//            } catch (Exception $e) {
//                // ID exists, but not in this context; abort
//                error(Error\Mapper::ERROR_INFORMATION_MISSING);
//            }
//        } else {
//            $group = $groupMapper->findOrCreateOneForFolderByName($folderId, $groupName);
//        }
//        $groupMapper->save($group);
//        $groupList[$groupName] = $group;
//    }
//
//    // Now, find the criterion: if it doesn't exist create it
//    $group         = $groupList[$groupName];
//    $criterionName = $critData['name'];
//
//    try {
//        $criteria = $criterionMapper->findOneByColumnList([
//            'id'              => $critID,
//            'rubric_group_id' => $group->getId(),
//        ]);
//        $criteria->setName($critData['name']);
//
//        //Do not edit criteria that are in use on a class page, can edit in course though
//        $usage = Rubric\UsageMapper::getInstance()->findByColumnList(['criterion_id' => $critID]);
//        if (count($usage) && !($folder->hasCourseSpecification() || $folder->hasUnitSpecification())) {
//            continue; // possibly throw an error of some kind
//        }
//    } catch (Exception $e) {
//        $criteria = new Rubric\Criterion();
//        $criteria->setGroup($group);
//        $criteria->setName($critData['name']);
//    }
//
//    $criterionMapper->save($criteria);
//}
//
//
//if ($page::detectAjax()) {
//    die('Criteria updated.');
//}
//$page::localRedir('/homepage/' . $folderId);
