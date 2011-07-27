<?php
/**
 * Assessment deletion. Sections, Questions and Answers are all softdeleted if
 * the assessment is softdeleted.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   2010 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 */
require_once('../library/survey/Survey.php');
require_once('../library/survey/SurveyFactory.php');
require_once('../library/survey/SurveySection.php');
require_once('../library/group.php');

/* Get a list of questions and answers to softdelete
 */
$assessHandler = new Survey();
$questionHandler = new SurveyQuestion();
$answerHandler = new SurveyAnswer();
$sectionHandler = new SurveySection();
$groupHandler = new Group();

if (!$groupHandler->isSuperuser()) {
	if (!$assessHandler->isOwner($this->arg[0])) {
		error(126);
	}
}

/* Proceed regardless of whether the section has been previously erased or not
 *

if ($assessHandler->isDeleted($this->arg[0])) {
	error(112);
}

 *
 */

$assess = $assessHandler->getByID($this->arg[0]);
$sectionList = $sectionHandler->getList($this->arg[0]);

if (is_array($sectionList) && count($sectionList)) {
    foreach($sectionList as $currentSection) {
        $section = $sectionHandler->getByID($currentSection['section_id']);
        $questionList = $questionHandler->getList($currentSection['section_id']);

		if (is_array($questionList) && count($questionList)) {
			foreach($questionList as $currentQuestion) {
				$question = $questionHandler->getByID($currentQuestion['question_id']);
				$answer = $answerHandler->getForQuestion($currentQuestion['question_id']);

				/* Perform soft delete
				 */
				$questionHandler->update(array(
					'question_id' => $currentQuestion['question_id'],
					'deleted_at' => 'NOW()',
				));

				$answerHandler->update(array(
					'answer_id' => $answer['answer_id'],
					'deleted_at' => 'NOW()',
				));
			}
		}

        $section['deleted_at'] = 'NOW()';
        $sectionHandler->update($section);
    }
}

/* Remove assess
 */
$assessHandler->update(array(
    'assess_id' => $this->arg[0],
    'deleted_at' => 'NOW()'
));

$this->setFile(TEMPLATE_content, 'survey/deleted.html');

$this->setVar(array(
	'CATEGORY_ID' => $assess['category_id'],
));
?>
