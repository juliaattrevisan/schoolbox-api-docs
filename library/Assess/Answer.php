<?php
/**
 * Assess_Answer
 *
 * All AnswerTypes inherit from this class.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   2010 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 */
class Assess_Answer extends Evaluation_Answer {


	/**
	 * Get an instance of a responseHandler upon instantiation
	 */
    public function __construct() {
        parent::__construct(new Assess_Response(), new Assess_Question());
    }
}
