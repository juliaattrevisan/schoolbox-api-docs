<?php
/**
 * A Summary page for assessment creation methods to return to.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   2010 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 */
require_once('../library/assess/assess.php');
require_once('../library/assess/assess-section.php');
require_once('../library/assess/assess-attempt.php');

$assessHandler = new Assess();
$sectionHandler = new AssessSection();
$attemptHandler = new AssessAttempt();

$assess = $assessHandler->getIntro($this->arg[0]);
$allSummary = $assessHandler->getIntroSummary($this->arg[0]);
$allSections = $sectionHandler->getList($assess['assess_id']);

if (!$assess) {
    error(112);
}

if (!$assessHandler->isOwner($this->arg[0])) {
    error(125);
}

$this->setVar(array(
    'ASSESS_ASSESS_ID' => $this->arg[0],
    'ASSESS_TITLE' => $assess['title'],
    'ASSESS_DESCRIPTION' => $assess['description'],
    'ASSESS_START' => date('jS F Y, g:i:sa', strtotime($assess['start'])),
    'ASSESS_FINISH' => date('jS F Y, g:i:sa', strtotime($assess['finish'])),
    'FIRST_SECTION' => $allSections[0]['section_id'],
));

$this->setFile(TEMPLATE_content, 'assess/summary.html');
$this->setBlock(TEMPLATE_content, 'section', 'section_block');
$this->setTitle('Assessment: ' . $assess['title']);

$this->setVar(array(
    'ASSESS_DURATION' => $assess['duration'] == Assess::UNLIMITED_DURATION ? 'Unlimited' : $assessHandler->sec2hms($assess['duration']),
));

/* Section list and question count
 */
if (is_array($allSummary) && count($allSummary)) {
    foreach($allSummary as $currentSummary) {
        $this->setVar(array(
            'SECTION_SECTION_ID' => $currentSummary['section_id'],
            'SECTION_SEQUENCE' => $currentSummary['sequence'],
            'SECTION_TITLE' => $currentSummary['title'],
            'SECTION_NUMQUESTIONS' => $currentSummary['numQuestions'],
        ));

        $this->parse('section_block', 'section', 'true');
    }
}
?>