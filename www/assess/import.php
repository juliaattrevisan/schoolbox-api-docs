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
require_once('../library/assess/assess.php');
require_once('../library/assess/assess-factory.php');
require_once('../library/assess/assess-section.php');

$factory = new AssessFactory();
$assessHandler = new Assess();
$sectionHandler = new AssessSection();

$data = file_get_contents('assess/testxml');

$xml = simplexml_load_string($data);

/* Set up assess
 */
$assess_id = $assessHandler->create(array(
    'category_id' => scrubTEXT(37),
    'user_id'     => $_SESSION['id'],
    'title'       => scrubTEXT($xml->title),
    'description' => scrubTEXT($xml->description),
    'start'       => scrubTEXT($xml->start),
    'finish'      => scrubTEXT($xml->finish),
    'duration'    => scrubTEXT($xml->duration),
    'allow_resit' => scrubTEXT($xml->allow_resit),
    'created_at'  => 'NOW()',
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

        $questionHandler = $factory->createQuestionModel(scrubTEXT($currentQuestion->typeid));
        $answerHandler = $factory->createAnswerModel(scrubTEXT($currentQuestion->typeid));

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

die();
?>
