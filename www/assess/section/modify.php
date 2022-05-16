<?php

/**
 * Section modification.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Schoolbox\Breadcrumbs;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Learning\Assessment\Redirect;

$assessHandler = new Assess_Main();
$groupHandler = new Group();

if (!empty($_POST)) {
    if (!$assessHandler->isValidUrlAccess($_POST['assess_id'])) {
        $this->pageNotFound();
    }


    $returnUrl = $_POST[Redirect::RETURN_URL] != '' ? "?" . Redirect::RETURN_URL . "={$_POST[Redirect::RETURN_URL]}" : "";

    $assess = $assessHandler->getByID($_POST['assess_id']);

    if ($assessHandler->isDeleted($_POST['assess_id'])) {
        error(112);
    }

    if (!$assessHandler->isOwner($assess['assess_id']) && ($groupHandler->getPermission($assess['category_id']) < UserAccess::ACCESS_WRITE)) {
        error(130);
    }

    $sectionHandler = new Assess_Section();

    $sectionHandler->update(array(
        'assess_id'   => (int)$_POST['assess_id'],
        'section_id'  => (int)$_POST['section_id'],
        'sequence'    => (int)$_POST['sequence'],
        'title'       => $_POST['title'],
        'random'      => $_POST['random'] ?? 0,
        'pool'        => (int)($_POST['pool'] ?? 0),
        'description' => $_POST['description'],
    ));

    /* Redirect to summary
     */
    SitePage::localRedir('/assess/summary/' . $_POST['assess_id'] . $returnUrl);
} else {
    $returnUrl = $_GET[Redirect::RETURN_URL] != '' ? "?" . Redirect::RETURN_URL . "={$_GET[Redirect::RETURN_URL]}" : "";

    if (!is_numeric($this->arg[0])) {
        error(112);
    }

    $sectionHandler = new Assess_Section();
    $section = $sectionHandler->getByID($this->arg[0]);

    if (!$assessHandler->isValidUrlAccess($section['assess_id'])) {
        $this->pageNotFound();
    }

    if ($section) {
        $assess = $assessHandler->getByID($section['assess_id']);
    } else {
        error(115);
    }

    if ($assessHandler->isDeleted($section['assess_id'])) {
        error(112);
    }

    if (!$assessHandler->isOwner($section['assess_id']) && ($groupHandler->getPermission($assess['category_id']) < UserAccess::ACCESS_WRITE)) {
        error(130);
    }

    try {
        $folderMapper = new FolderMapper();
        $folder = $folderMapper->findById($assess['category_id']);
    } catch (\Exception $e) {
        error(117);
    }

    /* Render modify page
     */
    $this->setFile(TEMPLATE_content, 'assess/section/modify.html');
    $this->setBlock(TEMPLATE_content, 'allQuestions', 'allQuestions_block');
    $this->setTitle('Create Section');

    $this->setVar(array(
        'ASSESS_ASSESS_ID'     => $section['assess_id'],
        'RETURN_URL_NAME'      => Redirect::RETURN_URL,
        'RETURN_URL'           => $_GET[Redirect::RETURN_URL],
        'ASSESS_KIND_LC'       => 'assess',
        'ASSESS_KIND'          => 'Quiz',
        'ASSESS_TITLE'         => Scrub::text($assess['title']),
        'ASSESS_CATEGORY_ID'   => $folder->getId(),
        'ASSESS_CATEGORY_NAME' => $folder->getName(),
        'ASSESS_DURATION'      => $assess['duration'] == Assess_Main::UNLIMITED_DURATION ? 'Unlimited' : $assessHandler->sec2hms($assess['duration']),
        'SECTION_ID'           => $section['section_id'],
        'SECTION_TITLE'        => Scrub::text($section['title']),
        'DESCRIPTION'          => Scrub::html($section['description']),
        'SEQUENCE'             => $section['sequence'],
        'RANDOM_SELECTED'      => $section['random'] == 0 ? '' : 'checked=\'checked\'',
        'POOL'                 => $section['pool'] > 0 ? $section['pool'] : ''
    ));

    /* Render out breadcrumbs
     */
    $trail = new \Schoolbox\Breadcrumbs();

    // get a crunchy biscuit base
    $crumbs = $trail->getFolderCrumbs($folder, $_GET[Redirect::RETURN_URL]);

    // sprinkle some crumbs on top
    $crumbs = array_merge($crumbs, [
        [
            'text' => $assess['title'],
            'href' => "/assess/summary/{$assess['assess_id']}{$returnUrl}",
            'class' => '',
        ],
        [
            'text' => $section['title'],
            'href' => "",
            'class' => 'active',
        ]
    ]);

    // bake in a moderate oven for 40 minutes
    $trail->renderIntoSitePage($this, 'BREADCRUMB', $crumbs);
}
