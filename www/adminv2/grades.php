<?php
/**
 * Grades admin page
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

use Schoolbox\Assessment\Component\SubmissionBox\GradeLevel;

if (!$_SESSION['user']->isSuperuser()) {
    error(19);
}

/** @var SitePage $this */
$this->setFile(TEMPLATE_content, 'adminv2/grades.html');
$this->setTitle('Admin - Grades');

// include the admin navigation
$nav = new AdminV2_Nav($this->getDi()->get('config'));
$nav->parse($this, 'grades');

$this->setBlock(TEMPLATE_content, 'submission_grade_group', 'submission_grade_group_block');
$this->setBlock('submission_grade_group', 'submission_grade', 'submission_grade_block');

// Load groups, with their grades
$this->setVar('submission_grade_group_block', '');

foreach (GradeLevel\Group\Mapper::getInstance()->findAllWithGradeLevels() as $group) {
    $this->setVar('submission_grade_block', '');
    foreach ($group->getGradeLevels() as $level) {
        $this->setVar(
            array(
                'SUBMISSION_GRADE_ID'          => Scrub::text($level->getId()),
                'SUBMISSION_GRADE_CAPTION'     => Scrub::text($level->getCaption()),
                'SUBMISSION_GRADE_LOWER_BOUND' => Scrub::text($level->getLowerBound()),
                'SUBMISSION_GRADE_RAW_GRADE'   => Scrub::text($level->getRawGrade()),
            )
        );
        $this->parse('submission_grade_block', 'submission_grade', true);
    }
    $this->setVar(
        array(
            'SUBMISSION_GRADE_GROUP_ID'   => Scrub::text($group->getId()),
            'SUBMISSION_GRADE_GROUP_NAME' => Scrub::text($group->getName()),
        )
    );
    $this->parse('submission_grade_group_block', 'submission_grade_group', true);
}
