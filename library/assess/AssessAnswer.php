<?php
/**
 * AssessAnswer class. All AnswerTypes inherit from this class.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   2010 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 */
require_once($_SERVER['DOCUMENT_ROOT'] . '/../library/evaluation/EvaluationAnswer.php');

class AssessAnswer extends EvaluationAnswer {
    protected $_table = 'assess_answer';
    protected $_index = 'answer_id';

    protected $allowedFields = array('answer_id', 'question_id', 'sequence', 'answer', 'deleted_at');
}
?>
