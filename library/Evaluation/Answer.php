<?php
/**
 * Evaluation_Answer
 *
 * All AnswerTypes inherit from this class.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   2010 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 */
abstract class Evaluation_Answer extends DeprecatedModel
{
    public $_table = 'assess_answer';
    public $_index = 'answer_id';

    protected $allowedFields = array('answer_id', 'question_id', 'sequence', 'answer', 'deleted_at');

    /* Whether or not to include HTML formatting when exporting CSV results
      */
    protected $legendHTML = false;

    /* Template file used for graphing
      */
    protected $graphTemplateFile = null;

    /* Show letters 'A' or the full text corresponding to it
     */
    protected $useLongAnswers = false;

	/**
	 * An instance of Assess/Survey ResponseHandler
	 *
	 * @var Survey_Response
	 */
	protected $responseHandler = null;

	/**
	 * An instance of Assess/Survey QuestionHandler
	 *
	 * @var null
	 */
	protected $questionHandler = null;

    /**
     * Constructor.
     *
     * Run parent constructor
     */
    public function __construct()
    {
        parent::DeprecatedModel();

		$this->setup();
    }

	/**
	 * Hook method
	 */
	protected function setup() {
	}

    /**
     * Sets legendHTML
     *
     * @param $val
     *
     * @return void
     */
    public function setLegendHTML($val)
    {
        $this->legendHTML = $val;
    }

    /**
     * Gets legendHTML
     *
     * @return bool
     */
    public function getLegendHTML()
    {
        return $this->legendHTML;
    }

	/**
	 * Output configuration settings in modify
	 */
	public function configure() {
	}

    /**
     * Output configuration tags in xml
     *
     * @param $page
     * @param $config
     */
    public function configureXml($page, $config) {
        if (strlen($config)) {
            $options = json_decode($config);

            /* std objects cast as arrays -_- since json.stringify
             * compiles them into anonymous objects
             */
            $keys = array_keys((array)$options);

            if (is_array($keys) && count($keys)) {
                foreach($keys as $currentConfigOption) {
                    $page->setVar(array(
                        'ANSWER_CONFIGURATION_NAME' => $currentConfigOption,
                        'ANSWER_CONFIGURATION_VALUE' => $options->$currentConfigOption
                    ));

                    $page->parse('answer_configuration_block', 'answer_configuration', true);
                }
            }
            else {
                $page->setVar(array(
                    'answer_configuration_block' => ''
                ));
            }
        }
    }

	/**
	 * Hook method
	 */
	protected function configureResponse($page, $question_id) {
	}

    /**
     * Gets the Answer record for a particular Question.
     *
     * @param  $question_id
     * @return array|bool
     */
    public function getForQuestion($question_id)
    {
        $query = "SELECT *
                 FROM {$this->_table}
                 WHERE question_id = '$question_id'";

        return $this->db->getRow($query);
    }

    /**
     * Gets possible answer(s) for a particular Question. This is used when
     * attempting the test and might be customised for each Answer type.
     *
     * @param  $question_id
     * @return array|bool
     */
    public function getByQuestionID($question_id)
    {
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
     * @param  $section_id
     * @param  $question_id
     * @param  $processedResponse
     *
     * @return array
     */
    public function saveResponse($section_id, $question_id, $processedResponse)
    {
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
        $existing = $this->getResponseHandler()->isResponseExists($_SESSION['attempt_id'], $question_id);
        $response_id = $existing['response_id'];

        if (is_numeric($response_id)) {
            $response['response_id'] = $response_id;

            if ($this->getResponseHandler()->update($response)) {
                $result['response_id'] = $response_id;
            }
        } else {
            $result['response_id'] = $this->getResponseHandler()->create($response);
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
     * @param  $data
     * @param  $response_id
     * @return array
     */
    public function saveReviewedResponse($data, $response_id)
    {
        $reviewedResponse = array(
            'response_id' => $response_id,
            'comment' => $data['comment'],
            'mark' => $data['reviewedMarks'],
        );

        if ($this->getResponseHandler()->update($reviewedResponse)) {
            return $reviewedResponse;
        }

        return array();
    }

    /**
     * Get graph template file
     *
     * @return string
     */
    protected function getGraphTemplateFile()
    {
        return $this->graphTemplateFile;
    }

    /**
     * Find out whether to use long answers or just the letter code
     * Used in exports
     *
     * @return bool
     */
    protected function getUseLongAnswers()
    {
        return $this->useLongAnswers;
    }

    /**
     * Export response to csv - manually enforced in each subclass due to handlers
     */
    public function exportResponseToCSV($attempt_id, $question_id) {
    }

    /**
     * Identical to exportResponseToCSV(), which generates data on the
     * resultsForQuestion screen and the csv file itself, unless otherwise
     * specified.
     *
     * This special method for having a different csv export response value, than
     * to the one you see on resultsForQuestion screens (which by default use
     * the same method), is another level of abstraction that isn't needed
     * in most places
     *
     * @param $attempt_id
     * @param $question_id
     * @return mixed
     */
    public function resultsForQuestion($attempt_id, $question_id) {
        $data = $this->exportResponseToCSV($attempt_id, $question_id);

        $data['responses'] = Scrub::text($data['response']);

        return $data;
    }

	/* Getters/Setters
 	 */

	/**
	 * Get pluginReview
	 *
	 * @return string
	 */
	public function getPluginReview() {
		return $this->pluginReview;
	}

	/**
	 * Set question handler
	 *
	 * @param null $questionHandler
	 */
	protected function setQuestionHandler($questionHandler) {
		$this->questionHandler = $questionHandler;
	}

	/**
	 * Get question handler
	 *
	 * @return null
	 */
	protected function getQuestionHandler() {
		return $this->questionHandler;
	}

	/**
	 * Set response handler
	 *
	 * @param Survey_Response $responseHandler
	 */
	protected function setResponseHandler($responseHandler) {
		$this->responseHandler = $responseHandler;
	}

	/**
	 * Get response handler
	 *
	 * @return Survey_Response
	 */
	protected function getResponseHandler() {
		return $this->responseHandler;
	}
}
