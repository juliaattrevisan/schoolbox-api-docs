<?php
/**
 * Survey_Answer
 *
 * All AnswerTypes inherit from this class.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   2010 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 */
abstract class Survey_Answer extends Evaluation_Answer {
    public $_table = 'assess_answer';
    public $_index = 'answer_id';

    protected $allowedFields = array('answer_id', 'question_id', 'sequence', 'answer', 'deleted_at');

	/**
	 * Setup
	 */
    protected function setup() {
        $this->setResponseHandler(new Survey_Response());
    }
}

