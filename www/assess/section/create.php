<?php

/**
 * Section creation.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentDataSource;
use Schoolbox\Learning\Assessment\Project;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Learning\Assessment\Redirect;

$assessHandler = new Assess_Main();
$groupHandler = new Group();

/** @var SitePage $this */
if (!empty($_POST)) {
    if (!$assessHandler->isValidUrlAccess($_POST['assess_id'])) {
        $this->pageNotFound();
    }

    $assess = $assessHandler->getByID($_POST['assess_id']);

    if (!$assessHandler->isOwner($assess['assess_id']) && ($groupHandler->getPermission($assess['category_id']) < UserAccess::ACCESS_WRITE)) {
        error(130);
    }

    if ($assessHandler->isDeleted($assess['assess_id'])) {
        error(112);
    }

    /* Create new Section
     */
    $sectionHandler = new Assess_Section();

    $returnUrl = $_POST[Redirect::RETURN_URL] != '' ? "?" . Redirect::RETURN_URL . "={$_POST[Redirect::RETURN_URL]}" : "";

    $section = [
        'assess_id'   => (int)$_POST['assess_id'],
        'sequence'    => (int)$_POST['sequence'],
        'title'       => $_POST['title'],
        'random'      => $_POST['random'] ?? 0,
        'pool'        => (int)($_POST['pool'] ?? 0),
        'description' => $_POST['description'],
    ];

    $section_id = $sectionHandler->create($section);

    /* Redirect to question creation for this section
     */
    SitePage::localRedir('/assess/question/create/' . $section_id . $returnUrl);
} else {
    if (empty($this->arg[0])) {
        error(51);
    }

    if (!$assessHandler->isValidUrlAccess($this->arg[0])) {
        $this->pageNotFound();
    }

    if ($assessHandler->isDeleted($this->arg[0])) {
        error(112);
    }

    /* Find out whether the test exists to which you are attempting to add a
     * new Section
     */
    $assess = $assessHandler->getByID($this->arg[0]);

    if (!$assessHandler->isOwner($assess['assess_id']) && ($groupHandler->getPermission($assess['category_id']) < UserAccess::ACCESS_WRITE)) {
        error(130);
    }

    if (empty($assess)) {
        error(112);
    }

    try {
        $folderMapper = new FolderMapper();
        $folder = $folderMapper->findById($assess['category_id']);
    } catch (\Exception $e) {
        error(117);
    }


    /* Render create page
     */
    $this->setFile(TEMPLATE_content, 'assess/section/create.html');
    $this->setTitle('Create Section');

    $sectionHandler = new Assess_Section();

    $this->setVar(array(
        'ASSESS_ASSESS_ID'     => $this->arg[0],
        'RETURN_URL_NAME'      => Redirect::RETURN_URL,
        'RETURN_URL'           => $_GET[Redirect::RETURN_URL] ?? null,
        'ASSESS_KIND_LC'       => 'assess',
        'ASSESS_KIND'          => 'Quiz',
        'ASSESS_TITLE'         => Scrub::text($assess['title']),
        'ASSESS_CATEGORY_ID'   => $folder->getId(),
        'ASSESS_CATEGORY_NAME' => Scrub::text($folder->getName()),
        'ASSESS_DURATION'      => $assess['duration'] == Assess_Main::UNLIMITED_DURATION ? 'Unlimited' : $assessHandler->sec2hms($assess['duration']),
        'SECTION_ID'           => 0,
        'SEQUENCE'             => $sectionHandler->getNextSequence($this->arg[0])
    ));

    /** @var AssessmentDataSource $dataSource */
    $dataSource = $this->getDi()->get('assessmentDataSource');
    $assessment = $dataSource->findById($assess['assessment_id']);

    /* Render out breadcrumbs
     */
    $trail = new \Schoolbox\Breadcrumbs();

    // get a crunchy biscuit base
    $crumbs = $trail->getFolderCrumbs($folder, $_GET[Redirect::RETURN_URL] ?? '');

    $returnUrl = ($_GET[Redirect::RETURN_URL] ?? '') != '' ? "?" . Redirect::RETURN_URL . "={$_GET[Redirect::RETURN_URL]}" : "";

    if ($assessment->getContext() instanceof Project) {
        $crumbs = array_merge($crumbs, [
            [
                'text'  => $assessment->getContext()->getTitle(),
                'href'  => $this->getDi()->get('router')->generate('learningAssessment.getStatus', [
                    'assessmentId' => $assessment->getContext()->getId(),
                    'assessment'   => $assessment->getContext(),
                ]) . $returnUrl,
                'class' => '',
            ],
        ]);
    }

    // sprinkle some crumbs on top
    $crumbs = array_merge($crumbs, [
        [
            'text' => $assess['title'],
            'href' => "/assess/summary/{$assess['assess_id']}{$returnUrl}",
            'class' => '',
        ],
        [
            'text' => 'New Section',
            'href' => "",
            'class' => 'active',
        ]
    ]);

    // bake in a moderate oven for 40 minutes
    $trail->renderIntoSitePage($this, 'BREADCRUMB', $crumbs);
}
