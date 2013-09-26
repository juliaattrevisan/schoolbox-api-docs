<?php
/**
 * Assess_Answer_Numeric
 *
 * Handler for Numeric questions.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   2010 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 */
class Assess_Answer_Numeric extends Evaluation_Answer_Numeric {

	/**
	 * Get an instance of a responseHandler upon instantiation
	 */
    public function __construct() {
        parent::__construct();

        $this->responseHandler = new Assess_Response();
    }

	/**
	 * Automatically marks a response. Customised to be applicable to the way in
	 * which the response is actually stored.
	 *
	 * @param  $currentResponse
	 * @return void
	 */
    public function mark($currentResponse) {
		if ($currentResponse['answer'] == $currentResponse['response']) {
			$total = $currentResponse['marks'];
		}
		else {
			$total = 0;
		}

		/* Total marks for this question
		 */
		$marks = array(
			'response_id' => $currentResponse['response_id'],
			'mark' => $total,
		);

		$this->responseHandler->update($marks);
    }
}
