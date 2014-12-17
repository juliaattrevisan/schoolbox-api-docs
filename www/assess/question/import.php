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

SitePage::extendExecutionTime(300);

if (!is_numeric($this->arg[0])) {
    error(18);
}

if ($_FILES['importXML']['tmp_name']) {
    $data = file_get_contents($_FILES['importXML']['tmp_name']);
}
else {
    error(134);
}

$factory = new Assess_Factory();
$questionHandler = new Assess_Question();
$sectionHandler = new Assess_Section();
$assessHandler = new Assess_Main();
$groupHandler = new Group();

$currentSection = $sectionHandler->getById($this->arg[0]);
$assess = $assessHandler->getByID($currentSection['assess_id']);

if (!$currentSection) {
	error(115);
}

if ($sectionHandler->isDeleted($this->arg[0])) {
	error(115);
}

if (!$assessHandler->isOwner($assess['assess_id']) && ($groupHandler->getPermission($assess['category_id']) < WRITE_ACCESS)) {
    error(125);
}

$xml = simplexml_load_string($data);

if (is_numeric((string)$xml->typeid)) {
	$questionHandler = $factory->createQuestionModel(Scrub::text($xml->typeid));
	$answerHandler = $factory->createAnswerModel(Scrub::text($xml->typeid));

	$question_id = $questionHandler->create(array(
		'question_id' => '',
		'section_id' => $currentSection['section_id'],
		'sequence' => $questionHandler->getNextSequence($currentSection['section_id']),
		'type_id' => $xml->typeid,
		'marks' => $xml->marks,
		'question' => trim((string) $xml->questiontext->text),
	));

	if (method_exists($answerHandler, 'importMoodleAnswer')) {
		$answer_id = $answerHandler->importMoodleAnswer($xml->answers, $question_id);
	}

	SitePage::localRedir('/assess/question/modify/' . $question_id);
}
else {
	error(135);
}

