<?php
/**
 * SurveyAttempt
 *
 * Assessment Attempts are managed through this class.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   2010 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 */
require_once($_SERVER['DOCUMENT_ROOT'] . '/../library/evaluation/EvaluationAttempt.php');

class SurveyAttempt extends EvaluationAttempt {
    public $_table = 'assess_attempt';
    public $_index = 'attempt_id';
}
