<?php
/**
 * Section modification.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   2010 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 */
require_once '../library/group.php';

$assessHandler = new Survey_Main();
$groupHandler = new Group();

if (!$groupHandler->isStaff()) {
    error(130);
}

if (!empty($_POST)) {
	if ($assessHandler->isDeleted($_POST['assess_id'])) {
		error(112);
	}

    if (!$assessHandler->isOwner(Scrub::text($_POST['assess_id']))) {
        error(130);
    }

    $sectionHandler = new Survey_Section();

    $sectionHandler->update(array(
        'assess_id' => Scrub::text($_POST['assess_id']),
        'section_id' => Scrub::text($_POST['section_id']),
        'sequence' => Scrub::text($_POST['sequence']),
        'title' => Scrub::text($_POST['title']),
        'random' => Scrub::text($_POST['random']),
        'pool' => Scrub::text($_POST['pool']),
        'description' => Scrub::text($_POST['description']),
    ));

    /* Redirect to summary
     */
    SitePage::localRedir('/survey/summary/' . Scrub::text($_POST['assess_id']));
}
else {
    if (!is_numeric($this->arg[0])) {
        error(112);
    }

    $sectionHandler = new Survey_Section();
    $section = $sectionHandler->getByID($this->arg[0]);

    if (!$assessHandler->isOwner($section['assess_id'])) {
        error(130);
    }

	if ($assessHandler->isDeleted($section['assess_id'])) {
		error(112);
	}

    /* Render modify page
     */
    $this->setFile(TEMPLATE_content, 'survey/section/modify.html');
    $this->setBlock(TEMPLATE_content, 'allQuestions', 'allQuestions_block');
    $this->setTitle('Create Section');

    $assess = $assessHandler->getByID($section['assess_id']);

    $this->setVar(array(
        'ASSESS_ASSESS_ID' => $section['assess_id'],
        'ASSESS_TITLE' => $assess['title'],
        'SECTION_ID' => $section['section_id'],
        'TITLE' => $section['title'],
        'DESCRIPTION' => $section['description'],
        'SEQUENCE' => $section['sequence'],
        'RANDOM_SELECTED' => $section['random'] == 0 ? '' : 'checked=\'checked\'',
        'POOL' => $section['pool'],
    ));

    /* Show this Assess' contents
     */
    $this->setFile('assessContentsFragment', 'survey/contents.html');
    $this->setBlock('assessContentsFragment', 'section', 'section_block');
    $this->setBlock('section', 'question', 'question_block');

    $assessHandler = new Survey_Main();
    $assessHandler->renderAssessContents($assess['assess_id'], $this);

    $this->parse('allQuestions_block', 'assessContentsFragment', true);
}
