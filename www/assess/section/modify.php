<?php
/**
 * Section modification.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   2010 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 */
require_once '../library/assess/assess.php';
require_once '../library/assess/assess-question.php';
require_once '../library/assess/assess-section.php';

if (!empty($_POST)) {
    $sectionHandler = new AssessSection();

    $sectionHandler->update(array(
        'assess_id' => scrubTEXT($_POST['assess_id']),
        'section_id' => scrubTEXT($_POST['section_id']),
        'sequence' => scrubTEXT($_POST['sequence']),
        'title' => scrubTEXT($_POST['title']),
        'random' => scrubTEXT($_POST['random']),
        'pool' => scrubTEXT($_POST['pool']),
        'description' => scrubTEXT($_POST['description']),
    ));

    /* Redirect to Section modify
     */
    localRedir('/assess/section/modify/' . scrubTEXT($_POST['section_id']));
}
else {
    /* Render modify page
     */
    $this->setFile(TEMPLATE_content, 'assess/section/modify.html');
    $this->setBlock(TEMPLATE_content, 'allQuestions', 'allQuestions_block');
    $this->setTitle('Create Section');

    if (empty($this->arg[0])) {
        error();
    }

    $sectionHandler = new AssessSection();
    $section = $sectionHandler->getByID($this->arg[0]);

    $assessHandler = new Assess();
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
    $this->setFile('assessContentsFragment', 'assess/contents.html');
    $this->setBlock('assessContentsFragment', 'section', 'section_block');
    $this->setBlock('section', 'question', 'question_block');

    $assessHandler = new Assess();
    $assessHandler->renderAssessContents($assess['assess_id'], $this);

    $this->parse('allQuestions_block', 'assessContentsFragment', true);
}

?>
