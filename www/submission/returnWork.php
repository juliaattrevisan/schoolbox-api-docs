<?php

use \Schoolbox\Assessment\Component\SubmissionBox\ReturnedWorkMapper;
use \Schoolbox\Assessment\Component\SubmissionBoxMapper;
use \Schoolbox\UserMapper;

$this->setFile(TEMPLATE_content, 'submission/returnWork.html');
$this->setBlock(TEMPLATE_content, 'mark_type_percent', 'mark_type_percent_block');
$this->setBlock(TEMPLATE_content, 'mark_type_letter', 'mark_type_letter_block');
$this->setBlock(TEMPLATE_content, 'mark_type_raw', 'mark_type_raw_block');
$this->setBlock(TEMPLATE_content, 'mark_type_free', 'mark_type_free_block');
$this->setTab('submission');

if (is_numeric($this->arg[0]) && is_numeric($this->arg[1])) {
    $subHandler = new Submission;
    $subReturn = new SubmissionReturn;

    $userMapper = new UserMapper();
    $user = $userMapper->findById($this->arg[1]);

    $subReturn->securityCheck($this->arg[0]);

    $submissionBoxMapper = new SubmissionBoxMapper();
    $submissionBox = $submissionBoxMapper->findById($this->arg[0]);

    $this->setTitle('Grading ' . \Scrub::text($submissionBox->getCategory()->getName()) . ' for ' . \Scrub::text($submissionBox->getName()));


    $this->setVar(
        array(
            'CATEGORY'         => \Scrub::text($submissionBox->getCategory()->getName()),
            'ASSNAME'          => \Scrub::text($submissionBox->getName()),
            'BOX_ID'           => $submissionBox->getId(),
            'CAT_ID'           => $submissionBox->getCategory()->getId(),
            'DUEWORK_YEAR'     => date('Y', strtotime($submissionBox->getDueDate())),
            'DUEWORK_SEMESTER' => DueWork_All::getSemester(strtotime($submissionBox->getDueDate())),
            'DUEWORK_TYPE'     => $submissionBox->getWorkType()->getName(),
            'DUEWORK_DUEDATE'  => $submissionBox->getDueDate()->getLongDateOnly(),
            'GRADE_VISIBILITY'      => $submissionBox->getMarkVisibilityDescription(),
            'GRADE_VISIBILITY_CODE' => $submissionBox->getMarkVisibility(),
            'NAME'             => Scrub::text($user->formatName()),
            'SYN_ID'           => $user->getExternalId(),
            'ROLE'             => Scrub::text($user->getRole()->getName()),
            'UID'              => $user->getId(),
            'REDIRECT'         => "/submission/viewSubmission/{$submissionBox->getId()}",
        )
    );

    $marksheet = \Marksheet_Factory::build($submissionBox->getMarkType()->getSlug(), $submissionBox);
    $mark = $marksheet->retrieveMark($user->getId(), false, false);

    $this->setVar('MARK_TYPE_COMMENT', $marksheet->getComment());

    // blockify and clear all the marktypes
    foreach ($subHandler->getMarkTypes(true) as $type) {
        $this->setBlock(TEMPLATE_content, "mark_type_{$type['slug']}", "mark_type_{$type['slug']}_block");
        $this->setVar("mark_type_{$type['slug']}_block", '');
    }

    /* Render rubrics
     */
    $renderer = new \Rubric\Renderer\Horizontal($this, $submissionBox, $user);
    $renderer->render();

    /* Return
     */
    $returnedWorkMapper = new ReturnedWorkMapper();
    try {
        $return = $returnedWorkMapper->findOneByColumnList(
            array(
                'box_id' => $submissionBox->getId(),
                'owner'  => $user->getId()
            ),
            array('returned' => 'desc')
        );
        $comment = $return->getComment();
    } catch (\Schoolbox\Mapper\Exception $e) {
        // no return for this user, show a blank comment
        $comment = '';
    }
    $this->setVar('COMMENT', Scrub::text($comment));

    /* Parse out everything
     */
    $slug = $marksheet->getSlug();
    $this->parse("mark_type_{$slug}_block", "mark_type_{$slug}");
} else {
    error(2);
}
