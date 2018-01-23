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
	 * @var Evaluation_Response
	 */
	protected $responseHandler;

	/**
	 * @var Evaluation_Question
	 */
	protected $questionHandler;

    /**
     * Javascript plugin for reviewing
     *
     * @var string
     */
    protected $pluginReview = '';

    /**
     * @param Evaluation_Response $responseHandler
     * @param Evaluation_Question $questionHandler
     */
    public function __construct($responseHandler, $questionHandler)
    {
        parent::__construct();
        $this->responseHandler = $responseHandler;
        $this->questionHandler = $questionHandler;
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
     *
     * @param SitePage     $page
     * @param string|false $config
	 */
	public function configure(SitePage $page, $config) {
	}


    /**
     * @param SitePage $page
     * @param int      $attemptId
     * @param int      $question_id
     */
    abstract public function prepareResponse(SitePage $page, $attemptId, $question_id);


    /**
     * Output configuration tags in xml
     *
     * @param SitePage $page
     * @param string $config
     */
    public function configureXml(SitePage $page, $config) {
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
     * @param $data
     * @return mixed
     */
    public abstract function saveResponse($data);

    /**
     * @param  $attemptId
     * @param  $section_id
     * @param  $question_id
     * @param  $processedResponse
     *
     * @return array
     */
    protected function saveResponseInternal($attemptId, $section_id, $question_id, $processedResponse)
    {
        $response = array(
            'attempt_id' => $attemptId,
            'section_id' => $section_id,
            'question_id' => $question_id,
            'response' => $processedResponse,
            'updated_at' => 'NOW()',
        );

        /* If they have already responded to this question in this attempt,
         * then update existing row; otherwise create a new one
         */
        $existing = $this->getResponseHandler()->isResponseExists($attemptId, $question_id);
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

        $data['responses'] = $data['responsesData'] = Scrub::text($data['response']);

        return $data;
    }

    /**
     * Exports the answer to Moodle XML format.
     *
     * @param SitePage $page
     * @param int $question_id
     */
    abstract public function exportMoodleAnswer(SitePage $page, $question_id);

    /**
     * @param SimpleXMLElement $xmlObj
     * @param int $question_id
     */
    abstract public function importMoodleAnswer(SimpleXMLElement $xmlObj, $question_id);


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
	 * @param Assess_Response|Survey_Response $responseHandler
	 */
	protected function setResponseHandler($responseHandler) {
		$this->responseHandler = $responseHandler;
	}

	/**
	 * Get response handler
	 *
	 * @return Assess_Response|Survey_Response
	 */
	protected function getResponseHandler() {
		return $this->responseHandler;
	}
}
