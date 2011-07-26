<?php
/**
 * AssessAnswer class. All AnswerTypes inherit from this class.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   2010 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 */

/**
 * Description of AssessQuestion class
 *
 * @author aetworowsky
 */
class AssessAnswer extends DeprecatedModel {
    public $_table = 'assess_answer';
    public $_index = 'answer_id';
    public $responseHandler;

    protected $allowedFields = array('answer_id', 'question_id', 'sequence', 'answer', 'deleted_at');

    private $templatePath = '../templates/core/assess/answer';

	/**
	 * Get an instance of a responseHandler upon instantiation
	 */
    public function __construct() {
        parent::DeprecatedModel();

        $this->responseHandler = new AssessResponse();
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
     * Load answer template. If there's an answer file (i.e. format to be used
     * by the submitter is different to the one used by admin to create/modify)
     * then use that instead. Otherwise just use the create file.
     *
     * This was created because Fillgap question type shows questions in a
     * different way to that in which edit/modify it.
     */
//    function loadTemplate($type) {
//        $path = $this->templatePath . '/' . $type;
//
//        if (is_readable($path . '/answer.html')) {
//            return file_get_contents($path . '/answer.html');
//        }
//
//        return file_get_contents($path . '/create.html');
//    }

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
        $responseHandler = new AssessResponse();

        $r = $responseHandler->isResponseExists($_SESSION['attempt_id'], $question_id);
        $response_id = $r['response_id'];

        if (is_numeric($response_id)) {
            $response['response_id'] = $response_id;

            if ($responseHandler->update($response)) {
                $result['response_id'] = $response_id;
            }
        }
        else {
            $result['response_id'] = $responseHandler->create($response);
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
            'comment' => scrubTEXT($data['comment']),
            'mark' => scrubTEXT($data['reviewedMarks']),
        );

        if ($this->responseHandler->update($reviewedResponse)) {
            return $reviewedResponse;
        }

        return array();
    }
}
?>
