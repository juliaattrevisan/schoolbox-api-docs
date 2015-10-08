<?php
/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

use Schoolbox\Assessment\Component;
use Schoolbox\Assessment\Component\SubmissionBox\Submission;
use Schoolbox\Breadcrumbs;

/* /submission/inline/viewStudent/<submission_id>
 */
$submissionId = $this->arg[0];

try {
    $submission = Submission\FileMapper::getInstance()->findById($this->arg[0]);
} catch (Schoolbox\Mapper\Exception $e) {
    error(41);
    exit;
}

$submissionBoxMapper = new Component\SubmissionBoxMapper();
$submissionBox = $submissionBoxMapper->findById($submission->getSubmissionBox()->getId());

$currentStudent = $submission->getOwner();
$currentUid = $currentStudent->getId();

/* Add category codes
 */
$categoryCodeMapper = new \Schoolbox\Resource\CategoryCodeMapper();
$categoryCode = $categoryCodeMapper->findForCategory($submissionBox->getCategory()->getId());
$submissionBox->getCategory()->setCodes($categoryCode);

// Permission check
$groupHandler  = new Group();
$accessLevel   = $groupHandler->getPermission($submissionBox->getCategory()->getId());
$hasPermission = false;
if ($accessLevel >= Schoolbox\Permission\Access\UserAccess::ACCESS_WRITE) {
    $hasPermission = true;
} elseif ($_SESSION['user']->getId() == $currentUid) {
    if ($submissionBox->getMarkVisibility()
        >= Component\SubmissionBox::MARK_VISIBILITY_STUDENT
    ) {
        $hasPermission = true;
    }
} elseif ($groupHandler->isParentOfChild($currentUid)) {
    if ($submissionBox->getMarkVisibility()
        >= Component\SubmissionBox::MARK_VISIBILITY_PARENT_STUDENT
        && !Config::get('restrict_student_submissions')
    ) {
        $hasPermission = true;
    }
}
if (!$hasPermission) {
    error(19);
}
unset($groupHandler);

/* Rendering things out
 */
$this->setFile(TEMPLATE_content, 'submission/inlineGrading/viewStudent.html');


/* Render out breadcrumbs
 */
$trail = new Breadcrumbs(Schoolbox\Resource\Folder\Mapper::getInstance());
$trail->renderIntoSitePage($this, 'BREADCRUMB', [
    [
        'text' => 'Due Work',
        'href' => "/submission/dueWork",
        'class' => '',
    ],
    [
        'text' => $submissionBox->getCategory()->getName(),
        'href' => "/homepage/".$submissionBox->getCategory()->getId(),
        'class' => '',
    ],
    [
        'text' => Scrub::text($submissionBox->getName()),
        'href' => "/submission/submissionHistory/{$submissionBox->getId()}/{$currentStudent->getId()}",
        'class' => '',
    ],
    [
        'text' => 'Annotations',
        'href' => "#",
        'class' => 'active',
    ]
]);

/* Render injectable views
 */
$templateFactory = new \Crocodoc\Renderer\Factory($this, $submission, null, array());
$renderer = $templateFactory->produce();
$renderer->render();

$this->setVar(
    array(
        'INLINE_GRADING_SUBMISSION_ID' => $submission->getId(),
        'INLINE_GRADING_SUBMISSION_BOX_ID' => $submissionBox->getId(),
        'INLINE_GRADING_SUBMISSION_BOX_NAME' => $submissionBox->getName(),
        'INLINE_GRADING_STUDENT_FULL_NAME' => $currentStudent->getFullName(),
        'INLINE_GRADING_STUDENT_ID' => $currentStudent->getId(),
    )
);
