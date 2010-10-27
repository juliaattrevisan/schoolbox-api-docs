<?php
/**
 * Results page. This provides a list of Attempts that were made for a
 * particular Assessment, and the total marks obtained by the student.
 * Automatic marking of tests is handled here, where possible; otherwise, a
 * manual review is required to evaluate marks for certain questions which
 * cannot be automatically marked, e.g. short/long answer question types.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   2010 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 */
require_once('../library/assess/assess.php');
require_once('../library/assess/assess-factory.php');
require_once('../library/assess/assess-attempt.php');
require_once('../library/assess/assess-response.php');
require_once('../library/assess/assess-section.php');
require_once('../library/group.php');

$factory = new AssessFactory();
$responseHandler = new AssessResponse();
$assessHandler = new Assess();
$attemptHandler = new AssessAttempt();
$sectionHandler = new AssessSection();

$assess = $assessHandler->getByID($this->arg[0]);

if (!$assess) {
    error(112);
}

if (!$assessHandler->isOwner($this->arg[0])) {
    error(125);
}

/* Perform automated marking on responses which support this feature. Only
 * mark responses which haven't been automarked previously, which allows
 * teachers to customise response marks without being overwritten again.
 *
 * Once automated marking has been performed, remember which responses were
 * affected so we don't refetch them the next time the page loads.
 */
$allAttempts = $attemptHandler->getList($this->arg[0]);

if ($allAttempts) {
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
}

$responseHandler->updatedAutomatedMarks($markedResponseIDs);

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

if ($allAttempts) {
    foreach($allAttempts as $currentAttempt) {
        $firstSection = $sectionHandler->getFirst($this->arg[0]);

        /* Finish time. Show the datetime when the person clicked Finish.
         * Otherwise, show the last time their responses were updated, or that
         * they never even attempted the test.
         */
        if ($currentAttempt['finish'] != '0000-00-00 00:00:00') {
            $finish = date('jS F Y, g:i:sa', strtotime($currentAttempt['finish']));
            $timeTaken = $assessHandler->sec2hms($currentAttempt['timeTaken']);
        }
        else {
            $finish = $responseHandler->getLastSave($currentAttempt['attempt_id']);

            if (empty($finish)) {
                $finish = 'Never attempted';
            }
            else {
                $finish = date('jS F Y, g:i:sa', strtotime($finish));
            }

            $timeTaken = 'Incomplete';
        }

        /* The total available marks for this attempt
         */
        $totalAvailableMarks = $attemptHandler->getTotalMarks($this->arg[0], $currentAttempt['attempt_id']);

        if ($totalAvailableMarks > 0) {
            $percentage = round(100 * $currentAttempt['totalMarks'] / $totalAvailableMarks);
        }
        else {
            $percentage = 0;
        }

        /* Check when the test was reviewed
         */
        if ($currentAttempt['reviewed_at'] != '') {
            $reviewedAt = 'Reviewed on ' . date('jS F Y, g:i:sa', strtotime($currentAttempt['reviewed_at']));
        }
        else {
            $reviewedAt = 'Not reviewed';
        }

        /* Count how many responses haven't been automarked
         */
        $numNotAutomarked = $responseHandler->numNotAutomarked($currentAttempt['attempt_id']);

        $this->setVar(array(
            'ASSESS_ASSESS_ID' => $this->arg[0],
            'ASSESS_FIRST_SECTION_ID' => $firstSection['section_id'],
            'ATTEMPT_ATTEMPT_ID' => $currentAttempt['attempt_id'],
            'ATTEMPT_NAME' => $currentAttempt['lastname'] . ', ' . $currentAttempt['firstname'],
            'ATTEMPT_FINISH' => $finish,
            'ATTEMPT_TIME_TAKEN' => $timeTaken,
            'ATTEMPT_TOTAL_MARKS' => number_format($currentAttempt['totalMarks'], 2),
            'ATTEMPT_TOTAL_AVAILABLE_MARKS' => number_format($totalAvailableMarks, 2),
            'ATTEMPT_PERCENTAGE' => $percentage,
            'ATTEMPT_REVIEW_STATUS' => $reviewedAt,
            'ATTEMPT_UNMARKED_RESPONSES' => $numNotAutomarked,
        ));

        $this->parse('attempt_block', 'attempt', true);
    }
}
else {
    $this->setVar('attempt_block', '');
}
?>