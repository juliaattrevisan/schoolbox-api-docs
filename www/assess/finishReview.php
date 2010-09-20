<?php
/**
 * Review completion. Marks when an assessment review is finished.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   2010 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 */
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/assess/assess-attempt.php';

$attemptHandler = new AssessAttempt();

$attempt = $attemptHandler->getByID($this->arg[1]);

if (!$attempt) {
    error(118);
}

$attemptHandler->reviewed($this->arg[1]);

localRedir('/submission/listSubmission');
?>