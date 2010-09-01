<?php
require_once('../library/assess/assess.php');
require_once('../library/assess/assess-section.php');

$assessHandler = new Assess();
$sectionHandler = new AssessSection();

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
$this->setTitle('Assessment: ' . $assess['title']);

$this->setVar(array(
    'DURATION' => $assessHandler->sec2hms($assess['duration'])
));

/* Section list and question count
 */
foreach($allSummary as $currentSummary) {
    $this->setVar(array(
        'SECTION_SEQUENCE' => $currentSummary['sequence'],
        'SECTION_TITLE' => $currentSummary['title'],
        'SECTION_NUMQUESTIONS' => $currentSummary['numQuestions'],
    ));

    $this->parse('section_block', 'section', 'true');
}

$_SESSION['attempt_id'] = '';
?>