<?php
/**
 * Update for setting all raw grades.
 *
 * Each mark's raw grade is set to halfway between lower and upper bound
 * (which is the next highest lower bound).
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

require_once __DIR__ . '/../../../include/Bootstrap.php';

$mapper    = \Schoolbox\Assessment\Component\SubmissionBox\GradeLevel\Mapper::getInstance();
$oldGrades = $mapper->findAll();
$newGrades = new \Schoolbox\Collection();

/** @var \Schoolbox\Assessment\Component\SubmissionBox\GradeLevel\Model[] $oldGrades */
foreach ($oldGrades as $oldGrade) {
    $lowerBound = $oldGrade->getLowerBound();

//    if ($oldLowerBound == 0) {
//        $oldGrade->setRawGrade(0);
//        continue;
//    }

    // Find whatever the next highest grade is, in this grade group.
    $upperBound = null;
    foreach ($oldGrades as $nextGrade) {
        if ($nextGrade->getGroup()->getId() != $oldGrade->getGroup()->getId()) {
            continue;
        }
        if ($nextGrade->getId() == $oldGrade->getId()) {
            continue;
        }

        // This grade has a higher lower bound than the grade I'm updating.
        // If it's the lowest such lower bound, use this as the raw grade.
        $nextLowerBound = $nextGrade->getLowerBound();
        if ($nextLowerBound > $lowerBound) {
            if (!isset($upperBound) || $nextLowerBound < $upperBound) {
                $upperBound = $nextLowerBound;
            }
        }
    }

    // No lower bound found?
    // This must be at the top of the scale.
    if (!isset($upperBound)) {
        $upperBound = 100;
    }
    $oldGrade->setRawGrade(intval(($upperBound + $lowerBound) / 2));
}
$mapper->save($oldGrades);
