<?php
/**
 * Assessment commencement. If the Assessment allows people to re-sit the test,
 * then clear the current attempt_id. Otherwise, remember it so people can't
 * reset the assessment timer by repeatedly refreshing the intro page.
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

$this->setVar(array(
    'ASSESS_ASSESS_ID' => $this->arg[0],
    'ASSESS_TITLE' => $assess['title'],
    'ASSESS_DESCRIPTION' => $assess['description'],
    'FIRST_SECTION' => $allSections[0]['section_id'],
));

$this->setFile(TEMPLATE_content, 'assess/intro.html');
$this->setBlock(TEMPLATE_content, 'section', 'section_block');
$this->setBlock(TEMPLATE_content, 'startTest', 'startTest_block');
$this->setBlock(TEMPLATE_content, 'alreadyAttempted', 'alreadyAttempted_block');
$this->setTitle('Assessment: ' . $assess['title']);

$this->setVar(array(
    'DURATION' => $assess['duration'] == Assess::UNLIMITED_DURATION ? 'Unlimited' : $assessHandler->sec2hms($assess['duration']),
));

/* Section list and question count
 */
if (is_array($allSummary) && count($allSummary)) {
    foreach($allSummary as $currentSummary) {
        $this->setVar(array(
            'SECTION_SEQUENCE' => $currentSummary['sequence'],
            'SECTION_TITLE' => $currentSummary['title'],
            'SECTION_NUMQUESTIONS' => $currentSummary['numQuestions'],
        ));

        $this->parse('section_block', 'section', 'true');
    }

    $this->parse('startTest_block', 'startTest');
}
else {
    $this->setVar('section_block', '');
    $this->setVar('startTest_block', '');
}

/* If you have made an attempt on this test, or are in the process of attempting,
 * don't reset the attempt id if you aren't allowed to resit. This prevents
 * people from resetting the timer by revisiting the intro screen repeatedly.
 */
if (!$attemptHandler->userHasAttempted($this->arg[0], $_SESSION['id']) || $assessHandler->allowResit($this->arg[0])) {
    $_SESSION['attempt_id'] = '';

    $this->setVar('alreadyAttempted_block', '');
}
else {
    $this->setVar('startTest_block', '');
    $this->parse('alreadyAttempted_block', 'alreadyAttempted');
}

?>