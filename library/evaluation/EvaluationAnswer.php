<?php
/**
 * EvaluationAnswer
 *
 * All AnswerTypes inherit from this class.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   2010 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 */
abstract class EvaluationAnswer extends DeprecatedModel {
    public $_table = 'assess_answer';
    public $_index = 'answer_id';
    public $responseHandler;
    protected $allowedFields = array('answer_id', 'question_id', 'sequence', 'answer', 'deleted_at');

	/* Whether or not to include HTML formatting when exporting CSV results
	 */
	protected $legendHTML = false;

	/**
	 * Constructor.
	 *
	 * Run parent constructor
	 */
    public function __construct() {
        parent::DeprecatedModel();
    }

	/**
	 * Sets legendHTML
	 *
	 * @return
	 */
	public function setLegendHTML($val) {
		$this->legendHTML = $val;
	}

	/**
	 * Gets legendHTML
	 *
	 * @return
	 */
	public function getLegendHTML() {
		return $this->legendHTML;
	}

    /**
     * Gets the Answer record for a particular Question.
     *
     * @param <type> $question_id
     * @return <type>
     */
    public function getForQuestion($question_id) {
        $query = "SELECT *
                 FROM {$this->_table}
                 WHERE question_id = '$question_id'";

        return $this->db->getRow($query);
    }

    /**
     * Gets possible answer(s) for a particular Question. This is used when
     * attempting the test and might be customised for each Answer type.
     *
     * @param <type> $question_id
     * @return <type>
     */
    public function getByQuestionID($question_id) {
        $query = "SELECT *
                  FROM {$this->_table}
                  WHERE question_id = '$question_id'";

        return $this->db->getRow($query);
    }

    /**
     * Saves a response. The processedResponse is formatted by each Answertype's
     * handler so you can serialize it, store it in native format, or in
     * whatever format is required.
     *
     * @param <type> $response
     */
    public function saveResponse($section_id, $question_id, $processedResponse) {
        $response = array(
            'attempt_id' => $_SESSION['attempt_id'],
            'section_id' => $section_id,
            'question_id' => $question_id,
            'response' => $processedResponse,
            'updated_at' => 'NOW()',
        );

        /* If they have already responded to this question in this attempt,
         * then update existing row; otherwise create a new one
         */
        $existing = $this->responseHandler->isResponseExists($_SESSION['attempt_id'], $question_id);
        $response_id = $existing['response_id'];

        if (is_numeric($response_id)) {
            $response['response_id'] = $response_id;

            if ($this->responseHandler->update($response)) {
                $result['response_id'] = $response_id;
            }
        }
        else {
            $result['response_id'] = $this->responseHandler->create($response);
        }

        /* Compile resultant object: contains the question_id which was just
         * saved, and the response_id for its answer
         */
        $result['question_id'] = $question_id;

        return $result;
    }

    /**
     * Save the reviewed mark
     *
     * @param <type> $data
     */
    public function saveReviewedResponse($data, $response_id) {
        $reviewedResponse = array(
            'response_id' => $response_id,
            'comment' => Scrub::text($data['comment']),
            'mark' => Scrub::text($data['reviewedMarks']),
        );

        if ($this->responseHandler->update($reviewedResponse)) {
            return $reviewedResponse;
        }

        return array();
    }
}
