<?php
/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

/* /submission/inline/view/<box_id>/<user_id>
 */
$submissionBoxId = $this->arg[0];
$currentUid = $this->arg[1];

/* This is an optional argument
 */
$submissionId = $this->arg[2];

$submissionBoxMapper = new \Schoolbox\Assessment\Component\SubmissionBoxMapper();
$submissionBox = $submissionBoxMapper->findById($submissionBoxId);

/* Add category codes
 */
$categoryCodeMapper = new \Schoolbox\Resource\CategoryCodeMapper();
$categoryCode = $categoryCodeMapper->findForCategory($submissionBox->getCategory()->getId());
$submissionBox->getCategory()->setCodes($categoryCode);

/* Crappy handler
 */
$submissionHandler = new Submission();

$userMapper = new \Schoolbox\UserMapper();
$currentStudent = $userMapper->findById($currentUid);

/* Augment navigation data
 */
$currentAugmented = array(
    'currentStudent' => $currentStudent,
    'submission' => null,
    'navigation' => array(
        'id' => $currentStudent->getId(),
        'label' => $currentStudent->getLastname() . ', ' . $currentStudent->getFirstname(),
        'value' => '/submission/inlineGrading/view/' . $submissionBoxId . '/' . $currentStudent->getId()
    ),
);

/* Trim out only this student
 */
$submissionMapper = new \Schoolbox\Assessment\Component\SubmissionBox\SubmissionMapper();

/* Load a specific submission for this box/person
 */
if (is_numeric($this->arg[2])) {
    $submission = $submissionMapper->findById($this->arg[2]);

    if ($submission) {

        /* Apply mime type
         */
        $mimetypeMapper = new \Schoolbox\File\MimetypeMapper();
        $mime = $mimetypeMapper->findOneByColumn('mime', $submission->getMimeType()->getMime());
        $submission->setMimetype($mime);

        /* Stack it
         */
        $currentAugmented['submission'] = $submission;
    }
}

/* Rendering things out
 */
$user = $userMapper->findById($submission->getOwner()->getId());

if ($currentAugmented['submission'] != null) {
    $submissionId = $currentAugmented['submission']->getId();
}

$this->setFile(TEMPLATE_content, 'submission/inlineGrading/viewStudent.html');

/* Render injectable views
 */
$templateFactory = new \Crocodoc\Renderer\Factory($this, $currentAugmented['submission'], array());
$renderer = $templateFactory->produce();
$renderer->render();

$this->setVar(
    array(
        'INLINE_GRADING_SUBMISSION_ID' => $submissionId,
        'INLINE_GRADING_SUBMISSION_BOX_ID' => $submissionBox->getId(),
        'INLINE_GRADING_SUBMISSION_BOX_NAME' => $submissionBox->getName(),
        'INLINE_GRADING_STUDENT_FULL_NAME' => $user->getFullName(),
        'INLINE_GRADING_STUDENT_ID' => $user->getId(),
    )
);
