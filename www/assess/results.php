<?php
require_once('../library/assess/assess.php');
require_once('../library/assess/assess-attempt.php');

$assessHandler = new Assess();
$attemptHandler = new AssessAttempt();

$assess = $assessHandler->getByID($this->arg[0]);

/* Set up template
 */
$this->setFile(TEMPLATE_content, 'assess/results.html');
$this->setBlock(TEMPLATE_content, 'attempt', 'attempt_block');
$this->setTitle('Results for: ' . $assess['title']);

$this->setVar(array(
    'ASSESS_TITLE' => $assess['title']
));

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
    ));

    $this->parse('attempt_block', 'attempt', true);
}
?>