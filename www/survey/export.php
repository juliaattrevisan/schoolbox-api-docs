<?php
/**
 * Assessment export. Uses the handlers to export their question types into a
 * Moodle format.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   2010 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 */
require_once('../library/survey/survey.php');
require_once('../library/survey/survey-factory.php');
require_once('../library/survey/survey-section.php');

$factory = new SurveyFactory();
$assessHandler = new Survey();
$sectionHandler = new SurveySection();

if (!is_numeric($this->arg[0])) {
    error(112);
}

$assess = $assessHandler->getByID($this->arg[0]);

if (!$assess) {
    error(112);
}

if ($assessHandler->isDeleted($this->arg[0])) {
	error(112);
}

/* Set up template
 */
$this->setFile('xml', 'survey/exportToMoodle.html');
$this->setBlock('xml', 'section', 'section_block');
$this->setBlock('section', 'answerInputs', 'answerInputs_block');
$this->setBlock('section', 'question', 'question_block');

$this->setVar(array(
    'ASSESS_TITLE' => $assess['title'],
    'ASSESS_DESCRIPTION' => $assess['description'],
    'ASSESS_START' => $assess['start'],
    'ASSESS_FINISH' => $assess['finish'],
    'ASSESS_DURATION' => $assess['duration'],
    'ASSESS_ALLOW_RESIT' => $assess['allow_resit'],
));

$allSections = $sectionHandler->getList($this->arg[0]);

foreach($allSections as $currentSection) {
    $this->setVar(array(
        'SECTION_SECTION_ID' => $currentSection['section_id'],
        'SECTION_TITLE' => $currentSection['title'],
        'SECTION_DESCRIPTION' => $currentSection['description'],
        'SECTION_RANDOM' => $currentSection['random'],
        'SECTION_POOL' => $currentSection['pool'],
    ));

    $allQuestions = $sectionHandler->getContents($this->arg[0], $currentSection['section_id']);

    if ($allQuestions) {
        foreach($allQuestions as $currentQuestion) {

            /* This if() clause allows for sections containing no questions.
             * because they come out with getContents()
             */
            if ($currentQuestion['type_id']) {
                $answerHandler = $factory->createAnswerModel($currentQuestion['type_id']);

                /* Load answer template into answerOption block
                 */
                $this->setFile('answerFormFragment', 'assess/answer/' . $factory->getQuestionType($currentQuestion['type_id']) . '/exportToMoodle.html');
                $this->setBlock('answerFormFragment', 'answerOption', 'answerOption_block');

                /* Reset answer options from previous iteration
                 */
                $this->setVar('answerOption_block', '');

                $this->setVar(array(
                    'QUESTION_QUESTION' => $currentQuestion['question'],
                    'QUESTION_MARKS' => $currentQuestion['marks'],
                    'QUESTION_TYPE_ID' => $currentQuestion['type_id'],
                    'QUESTION_TYPE_MOODLE' => $factory->getMoodleQuestionType($currentQuestion['type_id']),
                ));

                /* Load individual answers
                 */
                $answerHandler->exportMoodleAnswer($this, $currentQuestion['question_id']);

                $this->parse('answerInputs_block', 'answerFormFragment', true);
                $this->parse('question_block', 'question', true);

                $this->setVar('answerFormFragment', '');
                $this->setVar('answerInputs_block', '');
            }
        }
    }
    else {
        $this->setVar('question_block', '');
    }

    $this->parse('section_block', 'section', true);
    $this->setVar('question_block', '');
}

/* Create a filename
 */
$filename = preg_replace('/[^a-zA-Z0-9\-\_]/', '_', $assess['title']) . '-' . date('YmdHis') . '.xml';

header('Content-type: text/xml');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$this->template->pparse('finaloutput', 'xml');

/* Prevent other template crap from rendering ;)
 */
die();
?>
