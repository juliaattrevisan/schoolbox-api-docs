<?php
require_once('../library/assess/assess.php');
require_once('../library/assess/assess-factory.php');
require_once('../library/assess/assess-attempt.php');
require_once('../library/assess/assess-response.php');

$factory = new AssessFactory();
$responseHandler = new AssessResponse();
$assessHandler = new Assess();
$attemptHandler = new AssessAttempt();

/* Perform automated marking on responses which support this feature. Only
 * mark responses which haven't been automarked previously, which allows
 * teachers to customise response marks without being overwritten again.
 *
 * Once automated marking has been performed, remember which responses were
 * affected so we don't refetch them the next time the page loads.
 */
$allAttempts = $attemptHandler->getList($this->arg[0]);

foreach($allAttempts as $currentAttempt) {
    $allResponses = $responseHandler->getAutomarkableResponses($this->arg[0], $currentAttempt['attempt_id']);

    if (is_array($allResponses)) {
        foreach ($allResponses as $currentResponse) {
            $answerHandler = $factory->createAnswerModel($currentResponse['type_id']);

            $answerHandler->mark($currentResponse);

            $markedResponseIDs[] = $currentResponse['response_id'];
        }
    }
}

$responseHandler->updatedAutomatedMarks($markedResponseIDs);

$assess = $assessHandler->getByID($this->arg[0]);

/* Set up template
 */
$this->setFile(TEMPLATE_content, 'assess/results.html');
$this->setBlock(TEMPLATE_content, 'attempt', 'attempt_block');
$this->setTitle('Results for: ' . $assess['title']);

$this->setVar(array(
    'ASSESS_TITLE' => $assess['title']
));

/* Now construct list of data
 */
$allAttempts = $attemptHandler->getList($this->arg[0]);

foreach($allAttempts as $currentAttempt) {

    /* Finish time
     */
    if ($currentAttempt['finish'] != '0000-00-00 00:00:00') {
        $finish = date('jS F Y, g:i:sa', strtotime($currentAttempt['finish']));
        $timeTaken = $assessHandler->sec2hms($currentAttempt['timeTaken']);
    }
    else {
        $finish = '';
        $timeTaken = '';
    }

    $this->setVar(array(
        'ASSESS_ASSESS_ID' => $this->arg[0],
        'ATTEMPT_ATTEMPT_ID' => $currentAttempt['attempt_id'],
        'ATTEMPT_NAME' => $currentAttempt['lastname'] . ', ' . $currentAttempt['firstname'],
        'ATTEMPT_FINISH' => $finish,
        'ATTEMPT_TIMETAKEN' => $timeTaken,
        'ATTEMPT_TOTALMARKS' => $currentAttempt['totalMarks'],
    ));

    $this->parse('attempt_block', 'attempt', true);
}
?>