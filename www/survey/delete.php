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

/* Get a list of questions and answers to softdelete
 */
$surveyHandler = new Survey_Main();
$questionHandler = new Survey_Question();
$answerHandler = new Survey_Answer();
$sectionHandler = new Survey_Section();
$groupHandler = new Group();

/* Proceed regardless of whether the section has been previously erased or not
 *

if ($surveyHandler->isDeleted($this->arg[0])) {
	error(112);
}

 *
 */

$survey = $surveyHandler->getByID($this->arg[0]);

if (!$surveyHandler->isOwner($this->arg[0]) && ($groupHandler->getPermission($survey['category_id']) < WRITE_ACCESS)) {
	error(126);
}

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
					'question_id' => $currentQuestion['question_id'],
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
$surveyHandler->update(array(
    'assess_id' => $this->arg[0],
    'deleted_at' => 'NOW()'
));

$this->setFile(TEMPLATE_content, 'survey/deleted.html');

$this->setVar(array(
	'CATEGORY_ID' => $survey['category_id'],
));
