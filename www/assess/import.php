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
require_once('../library/assess/Assess.php');
require_once('../library/assess/AssessSection.php');
require_once('../library/assess/AssessQuestion.php');
require_once('../library/assess/AssessAnswer.php');
require_once('../library/assess/AssessFactory.php');

if (!is_numeric($this->arg[0])) {
    error(18);
}

if ($_FILES['importXML']['tmp_name']) {
    $data = file_get_contents($_FILES['importXML']['tmp_name']);
}
else {
    error(134);
}

$assessHandler = new Assess();
$sectionHandler = new AssessSection();
$factory = new AssessFactory();

$xml = simplexml_load_string($data);

/* Set up assess
 */
$assess_id = $assessHandler->create(array(
    'category_id' => Scrub::text($this->arg[0]),
    'user_id' => $_SESSION['id'],
    'title' => Scrub::text($xml->title),
    'description' => Scrub::text($xml->description),
    'start' => Scrub::text($xml->start),
    'finish' => Scrub::text($xml->finish),
    'duration' => Scrub::text($xml->duration),
    'allow_resit' => Scrub::text($xml->allow_resit),
	'availability' => Scrub::text($xml->availability),
    'created_at' => 'NOW()',
));

if (!$assess_id) {
    error(112);
}

/* Process each section in the assess
 */
for ($i=0; $i < count($xml->section); $i++) {
    $currentSection = $xml->section[$i];

    $section_id = $sectionHandler->create(array(
        'section_id' => '',
        'assess_id' => $assess_id,
        'sequence' => $i+1,
        'title' => $currentSection->title,
        'description' => $currentSection->description,
        'random' => $currentSection->random,
        'pool' => $currentSection->pool,
    ));

    /* Process each question in the section
     */
    for ($j=0; $j < count($currentSection->question); $j++) {
        $currentQuestion = $currentSection->question[$j];

        $questionHandler = $factory->createQuestionModel(Scrub::text($currentQuestion->typeid));
        $answerHandler = $factory->createAnswerModel(Scrub::text($currentQuestion->typeid));

        $question_id = $questionHandler->create(array(
            'question_id' => '',
            'section_id' => $section_id,
            'sequence' => $j+1,
            'type_id' => $currentQuestion->typeid,
            'marks' => $currentQuestion->marks,
            'question' => trim((string) $currentQuestion->questiontext->text),
        ));

        if (method_exists($answerHandler, 'importMoodleAnswer')) {
            $answer_id = $answerHandler->importMoodleAnswer($currentQuestion->answers, $question_id);
        }
    }
}

localRedir('/assess/modify/' . $assess_id);
?>
