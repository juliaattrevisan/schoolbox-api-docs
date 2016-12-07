<?php

/*
 *
 * redirect to Redirection to 16.5 url
 *
 */
if (is_numeric($this->arg[0]) && is_numeric($this->arg[1])) {

    $submissionBoxMapper = new \Schoolbox\Assessment\Component\SubmissionBoxMapper();
    $submissionBox = $submissionBoxMapper->findById($this->arg[0]);
    $assessmentId= $submissionBox->getAssessmentId();

    SitePage::localRedir('/learning/responses/'.$assessmentId.'/'.$this->arg[1], null, 301); // assessmentId/userId
}
SitePage::pageNotFound();

/*
 * Old code
 */


use \Schoolbox\Assessment\Component\SubmissionBox\ReturnedWorkMapper;
use Schoolbox\Assessment\Component\SubmissionBox\Rubric\RubricMapper;
use \Schoolbox\Assessment\Component\SubmissionBoxMapper;
use \Schoolbox\UserMapper;
use \Schoolbox\Breadcrumbs;

$this->setFile(TEMPLATE_content, 'submission/returnWork.html');
$this->setBlock(TEMPLATE_content, 'mark_type_percent', 'mark_type_percent_block');
$this->setBlock(TEMPLATE_content, 'mark_type_letter', 'mark_type_letter_block');
$this->setBlock(TEMPLATE_content, 'mark_type_raw', 'mark_type_raw_block');
$this->setBlock(TEMPLATE_content, 'mark_type_free', 'mark_type_free_block');
$this->setTab('submission');

if (is_numeric($this->arg[0]) && is_numeric($this->arg[1])) {
    $subHandler = new Submission;
    $subReturn = new SubmissionReturn;

    try {
        $userMapper = new UserMapper();
        $user       = $userMapper->findById($this->arg[1]);
    }
    catch (\Schoolbox\Mapper\Exception $e) {
        error(170);
        exit();
    }

    try {
        $submissionBoxMapper = new SubmissionBoxMapper();
        $submissionBox       = $submissionBoxMapper->findById($this->arg[0]);
    }
    catch (\Schoolbox\Mapper\Exception $e) {
        error(17);
        exit();
    }

    if (!$submissionBox->hasAccess($_SESSION['user'], \Schoolbox\Permission\Access\UserAccess::ACCESS_WRITE)) {
        error(25);
    }

    $this->setTitle('Grading ' . \Scrub::text($submissionBox->getCategory()->getName()) . ' for ' . \Scrub::text($submissionBox->getName()));


    $this->setVar(
        array(
            'CATEGORY'              => \Scrub::text($submissionBox->getCategory()->getName()),
            'ASSNAME'               => \Scrub::text($submissionBox->getName()),
            'BOX_ID'                => $submissionBox->getId(),
            'BOX_GRADIENT'          => $submissionBox->getGradientIndex(),
            'CAT_ID'                => $submissionBox->getCategory()->getId(),
            'DUEWORK_TYPE'          => $submissionBox->getWorkType()->getName(),
            'DUEWORK_DUEDATE'       => $submissionBox->getDueDate()->getLongDateOnly(),
            'GRADE_VISIBILITY'      => $submissionBox->getMarkVisibilityDescription(),
            'GRADE_VISIBILITY_CODE' => $submissionBox->getMarkVisibility(),
            'GRADE_VISIBILITY_CSS'  => \Schoolbox\Assessment\Widgets::getCss($submissionBox->getMarkVisibility()),
            'NAME'                  => Scrub::text($user->formatName()),
            'SYN_ID'                => $user->getExternalId(),
            'ROLE'                  => Scrub::text($user->getRole()->getName()),
            'UID'                   => $user->getId(),
            'REDIRECT'              => "/submission/viewSubmission/{$submissionBox->getId()}",
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
    exit();
}


/* Render out breadcrumbs
 */
$trail = new Breadcrumbs(Schoolbox\Resource\Folder\Mapper::getInstance());
$term  = Schoolbox\Assessment\Term\Mapper::getInstance()->findForDate($submissionBox->getDueDate());
$trail->renderIntoSitePage($this, 'BREADCRUMB', [
    [
        'text' => 'Submissions',
        'href' => "/submission/listSubmission",
        'class' => '',
    ],
    [
        'text' => $submissionBox->getCategory()->getName(),
        'href' => "/submission/listSubmission/{$term->getCode()}/{$submissionBox->getCategory()->getId()}",
        'class' => '',
    ],
    [
        'text' => Scrub::text($submissionBox->getName()),
        'href' => "/submission/viewSubmission/{$submissionBox->getId()}",
        'class' => '',
    ],
    [
        'text' => Scrub::text($user->getFullName()),
        'href' => "/search/user/{$user->getId()}",
        'class' => 'active',
    ]
]);

/* Rubric's Cube
 */
// do fun rubric things
//$this->setBlock(TEMPLATE_content, 'mark_type_rubric', 'mark_type_rubric_block');

if ($submissionBox->isRubric()) {

    $grid = RubricMapper::getInstance()->findBySubmissionBoxAndStudent($submissionBox, $user);

    $markTypeSlug = $submissionBox->getMarkType()->getSlug();

    $context = [
        'rubric' => $grid->toContext(),
        'markType' => [
            'mode' => $markTypeSlug
        ]
    ];

    // retrieve the submission grade data
    if ($markTypeSlug == 'letter') {

        $group = $submissionBox->getGradeGroup();

        if (!$group->getGradeLevels()->count()) {
            // insufficient hydration
            $group->setGradeLevels(
                Schoolbox\Assessment\Component\SubmissionBox\GradeLevel\Mapper::getInstance()
                    ->findByColumn(
                        'submission_grade_group_id',
                        $group->getId(),
                        [ 'order' => [ 'lower_bound' => 'ASC' ] ]
                    )
            );
        }

        $levels = [];

        foreach ($group->getGradeLevels() as $level) {
            $levels[$level->getLowerBound()] = $level->getCaption();
        }

        $context['markType']['conf'] = $levels;
    }

    $this->setVar('RUBRIC', $this->getTwig()->render('@rubric/grid-markable.twig', $context));

//    $rb = new DueWork_Rubric_Lightbox($this);
//    $rb->render('RUBRIC', $submissionBox->getId(), $user->getId(), $submissionBox->canViewRubric($_SESSION['user']), true);
} else {
    $this->setVar('RUBRIC', '');
}
