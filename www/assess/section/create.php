<?php
/**
 * Section creation.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   2010 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 */
require_once '../library/assess/Assess.php';
require_once '../library/assess/AssessSection.php';
require_once '../library/assess/AssessQuestion.php';
require_once '../library/group.php';

$assessHandler = new Assess();
$groupHandler = new Group();

if (!$groupHandler->isStaff()) {
    error(130);
}

if (!empty($_POST)) {
	if ($assessHandler->isDeleted($_POST['assess_id'])) {
		error(112);
	}

    if (!$assessHandler->isOwner(scrubTEXT($_POST['assess_id']))) {
        error(130);
    }

    /* Create new Section
     */
    $sectionHandler = new AssessSection();
    $section_id = $sectionHandler->create($_POST);

    /* Redirect to question creation for this section
     */
    localRedir('/assess/question/create/' . $section_id);
}
else {
    if (empty($this->arg[0])) {
        error(51);
    }

	if ($assessHandler->isDeleted($this->arg[0])) {
		error(112);
	}

    if (!$assessHandler->isOwner($this->arg[0])) {
        error(130);
    }

    /* Find out whether the test exists to which you are attempting to add a
     * new Section
     */
    $assess = $assessHandler->getByID($this->arg[0]);

    if (empty($assess)) {
        error(112);
    }

    /* Render create page
     */
    $this->setFile(TEMPLATE_content, 'assess/section/create.html');
    $this->setBlock(TEMPLATE_content, 'allQuestions', 'allQuestions_block');
    $this->setTitle('Create Section');

    $sectionHandler = new AssessSection();

    $this->setVar(array(
        'ASSESS_ASSESS_ID' => $this->arg[0],
        'ASSESS_TITLE' => $assess['title'],
        'SECTION_ID' => '',
        'SEQUENCE' => $sectionHandler->getNextSequence($this->arg[0])
    ));

    /* Show this Assess' contents
     */
    $this->setFile('assessContentsFragment', 'assess/contents.html');
    $this->setBlock('assessContentsFragment', 'section', 'section_block');
    $this->setBlock('section', 'question', 'question_block');

    $assessHandler->renderAssessContents($assess['assess_id'], $this);

    $this->parse('allQuestions_block', 'assessContentsFragment', true);
}

?>
