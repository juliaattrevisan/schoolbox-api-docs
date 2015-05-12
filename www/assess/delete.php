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
$assessHandler = new Assess_Main();
$questionHandler = new Assess_Question();
$answerHandler = new Assess_Answer();
$sectionHandler = new Assess_Section();
$groupHandler = new Group();

$assessId = $this->arg[0];
$assess   = $assessHandler->getByID($assessId);

if (!$assessHandler->isOwner($assessId) && ($groupHandler->getPermission($assess['category_id']) < WRITE_ACCESS)) {
	error(126);
}

/* Proceed regardless of whether the section has been previously erased or not
 *

if ($assessHandler->isDeleted($assessId)) {
	error(112);
}

 *
 */

$sectionList = $sectionHandler->getList($assessId);

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
$assessHandler->update(array(
    'assess_id'  => $assessId,
    'deleted_at' => 'NOW()'
));

// Are there any attached tasks or task instances?
// Delete them, no questions asked.
// TODO move to the assess mapper's postSave callback once that's mapperized
$taskMapper         = new \Schoolbox\Assessment\Course\TaskMapper();
$taskInstanceMapper = new \Schoolbox\Assessment\Course\Task\InstanceMapper();
$taskMapper->delete($taskMapper->findByColumn('id', $assessId));
$taskInstanceMapper->delete($taskMapper->findByColumn('id', $assessId));

$this->setFile(TEMPLATE_content, 'assess/deleted.html');

$this->setVar(array(
	'CATEGORY_ID' => $assess['category_id'],
));
