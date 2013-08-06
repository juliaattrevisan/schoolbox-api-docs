<?php
/**
 * Section creation.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   2010 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 */

$assessHandler = new Survey_Main();
$groupHandler = new Group();

if (!empty($_POST)) {
    $assess = $assessHandler->getByID(Scrub::text($_POST['assess_id']));

    if (!$assessHandler->isOwner($assess['assess_id']) && ($groupHandler->getPermission($assess['category_id']) < WRITE_ACCESS)) {
        error(130);
    }

    if ($assessHandler->isDeleted(Scrub::text($_POST['assess_id']))) {
        error(112);
    }

    /* Create new Section
     */
    $sectionHandler = new Survey_Section();
    $section_id = $sectionHandler->create($_POST);

    /* Redirect to question creation for this section
     */
    SitePage::localRedir('/survey/question/create/' . $section_id);
} else {
    if (empty($this->arg[0])) {
        error(51);
    }

    if ($assessHandler->isDeleted($this->arg[0])) {
        error(112);
    }

    /* Find out whether the test exists to which you are attempting to add a
     * new Section
     */
    $assess = $assessHandler->getByID($this->arg[0]);

    if (empty($assess)) {
        error(112);
    }

    if (!$assessHandler->isOwner($assess['assess_id']) && ($groupHandler->getPermission($assess['category_id']) < WRITE_ACCESS)) {
        error(130);
    }

    /* Render create page
     */
    $this->setFile(TEMPLATE_content, 'survey/section/create.html');
    $this->setBlock(TEMPLATE_content, 'allQuestions', 'allQuestions_block');
    $this->setTitle('Create Section');

    $sectionHandler = new Survey_Section();

    $this->setVar(array(
        'ASSESS_ASSESS_ID' => $this->arg[0],
        'ASSESS_TITLE' => Scrub::text($assess['title']),
        'SECTION_ID' => '',
        'SEQUENCE' => $sectionHandler->getNextSequence($this->arg[0])
    ));

    /* Show this Assess' contents
     */
    $this->setFile('assessContentsFragment', 'survey/contents.html');
    $this->setBlock('assessContentsFragment', 'section', 'section_block');
    $this->setBlock('section', 'question', 'question_block');

    $assessHandler->renderAssessContents($assess['assess_id'], $this);

    $this->parse('allQuestions_block', 'assessContentsFragment', true);
}
