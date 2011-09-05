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
require_once('../library/assess/AssessFactory.php');
require_once('../library/assess/AssessSection.php');

if (!is_numeric($this->arg[0])) {
    error(18);
}

if ($_FILES['importXML']['tmp_name']) {
    $data = file_get_contents($_FILES['importXML']['tmp_name']);
}
else {
    error(134);
}

$factory = new AssessFactory();
$questionHandler = new AssessQuestion();
$sectionHandler = new AssessSection();

$currentSection = $sectionHandler->getById($this->arg[0]);

if (!$currentSection) {
	error(115);
}

if ($sectionHandler->isDeleted($this->arg[0])) {
	error(115);
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

	localRedir('/assess/question/modify/' . $question_id);
}
else {
	error(135);
}
?>
