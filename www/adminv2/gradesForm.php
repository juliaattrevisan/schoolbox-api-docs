<?php
/**
 * Grades admin form
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

use Schoolbox\Assessment\Component\SubmissionBox\GradeLevel;

// no sitepage allowed
if (isset($this)) {
    $this->localRedir('/adminv2/grades');
    exit;
}
require_once __DIR__.'/../../library/Bootstrap.php';

// Build sitepage and session, and get permissions
$page = new SitePage();
if (!$_SESSION['user'] instanceof Schoolbox\User && $_SESSION['user']->isSuperuser()) {
    error(19);
}

// Do stuff
$gradeMapper = GradeLevel\Mapper::getInstance();
$groupMapper = GradeLevel\Group\Mapper::getInstance();
foreach ($_POST['group'] as $groupId => $group) {
    // Save each group
    if (strlen($group['name']) > 0) {
        if (preg_match('/^new-/', $groupId)) {
            $groupModel = new GradeLevel\Group\Model();
        } else {
            $groupModel = $groupMapper->findById($groupId);
        }
        $groupModel->setName($group['name']);
        $groupMapper->save($groupModel);

        // Save each grade that is a part of the item
        foreach ($group['grade'] as $gradeId => $grade) {
            if (strlen($grade['lower_bound']) > 0 && strlen($grade['caption']) > 0) {
                if (preg_match('/^new-/', $gradeId)) {
                    $gradeModel = new GradeLevel\Model();
                    $gradeModel->setGroup($groupModel);
                } else {
                    $gradeModel = $gradeMapper->findById($gradeId);
                }
                $gradeModel
                    ->setLowerBound($grade['lower_bound'])
                    ->setRawGrade($grade['raw_grade'])
                    ->setCaption($grade['caption']);
                $gradeMapper->save($gradeModel);
            }
        }
    }
}
$page->localRedir('/adminv2/grades');
