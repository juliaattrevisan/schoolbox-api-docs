<?php
/**
 * Assess_Answer_Short
 *
 * Handler for Short Answer questions.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   2010 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 */
class Assess_Answer_Short extends Evaluation_Answer_Short {

	/**
	 * Javascript class to use when creating UI
	 *
	 * @var string
	 */
	protected $pluginReview = 'evaluationShortReview';

	/**
	 * Automatically marks a response. Customised to be applicable to the way in
	 * which the response is actually stored.
	 *
	 * @return void
	 */
    public function mark() {
    }
}
