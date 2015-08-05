<?php

/**
 * Survey_Answer_Short
 *
 * Handler for Short Answer questions.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   2010 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 */
class Survey_Answer_Signature extends Evaluation_Answer_Signature
{
    protected $graphTemplateFile = 'survey/answer/signature/graph.html';

    /* Use this to toggle between letters and actual answers
     */
    protected $useLongAnswers = true;

    /**
     * Setup
     */
    protected function setup()
    {
        $this->setResponseHandler(new Survey_Response());
        $this->setQuestionHandler(new Survey_Question());
    }

    /**
     * Graph the results for this answer type
     *
     * @param  $page
     * @param  $currentQuestion
     * @return void
     */
    public function graphResults($page, $currentQuestion)
    {
        $componentHandle = 'temp' . $currentQuestion['question_id'];

        $page->setFile($componentHandle, $this->getGraphTemplateFile());

        $page->parse('graph_block', $componentHandle);
    }

    /**
     * Generate signature representation differently for some screens and
     * for the csv file itself. The CSV file probably won't benefit from
     * having a numerical representation of svg graphics, so it uses
     * the states "Signed" and "Unsigned", whereas this draws the sig itself
     *
     * @param $attempt_id
     * @param $question_id
     * @return mixed
     */
    public function resultsForQuestion($attempt_id, $question_id) {
        $response = $this->getResponseHandler()->getByAttemptAndQuestion($attempt_id, $question_id);

        /* This is called to manufacture $sectionValues, but that's really
         * high up to keep passing around a sitepage just for one question
         */
        $page = new SitePage(false);
        $page->setLite(true);
        $page->setFile('signaturePad', 'survey/answer/signature/rendered.html');

        $page->setVar(array(
            'ID' => $attempt_id,
            'RESPONSE' => $response['response']
        ));

        $page->parse('renderedSignature', 'signaturePad');

        /* SVG data
         */
        $data['responses']     = $page->getVar('renderedSignature');
        $data['responsesData'] = '';

        /* Append commentary
         */
        $data['comment'] = $response['comment'];

        /* Append awarded mark
         */
        $data['mark'] = $response['mark'];

        return $data;
    }

    /**
     * Get the response for a particular question
     *
     * @param  $attempt_id
     * @param  $question_id
     *
     * @return array
     */
    public function exportResponseToCSV($attempt_id, $question_id)
    {
        $response = $this->getResponseHandler()->getByAttemptAndQuestion($attempt_id, $question_id);

        /* In case someone saves with empty signature, which is
         * represented by an empty array []
         */
        if (strlen($response['response']) > 2) {
            $data['response'] = 'Signed';
        } else {
            $data['response'] = 'Unsigned';
        }

        /* Append commentary
         */
        $data['comment'] = $response['comment'];

        /* Append awarded mark
         */
        $data['mark'] = $response['mark'];

        return $data;
    }

    /**
     * Resets answer tokens so a user can fill them in while actually taking
     * the test.
     *
     * Don't scrub this, because it breaks the json object
     *
     * @param  $page
     * @param  $question_id
     * @return void
     */
    public function prepareResponse($page, $question_id)
    {

        /* Optional configuration hook
         */
        $this->configureResponse($page, $question_id);

        $existing = $this->getResponseHandler()->isResponseExists($_SESSION['attempt_id'], $question_id);

        $currentAnswer = $this->getByQuestionID($question_id);

        if ($existing['response']) {
            $page->setVar(
                array(
                    'QUESTION_ID' => $question_id,
                    'QUESTION_PLUGIN' => $this->plugin,
                    'ANSWER_ID' => $currentAnswer['answer_id'],
                    'ANSWER' => $existing['response'],
                )
            );
        } else {
            $page->setVar(
                array(
                    'QUESTION_ID' => $question_id,
                    'QUESTION_PLUGIN' => $this->plugin,
                    'ANSWER_ID' => $currentAnswer['answer_id'],
                    'ANSWER' => '',
                )
            );
        }

        $page->parse('answerOption_block', 'answerOption', true);
    }

    /**
     * Show the response someone submitted and also show the correct answer.
     * Provide an option for the teacher to review the mark and adjust.
     *
     * @param  $page
     * @param  $question_id
     * @return void
     */
    public function reviewResponse($page, $question_id)
    {
        $existing = $this->getResponseHandler()->isResponseExists($_SESSION['attempt_id'], $question_id);

        $currentAnswer = $this->getByQuestionID($question_id);

        /* Removed this check because it was wrongly matching a blank saved
         * response with no response at all, preventing reviewed marks from
         * displaying properly.
         *
         * if ($existing['response'] != null) { ... } else { ... }
         */
        $page->setVar(
            array(
                'ANSWER_ID' => $currentAnswer['answer_id'],
                'ANSWER' => $existing['response'],
                'RESPONSE_ID' => $existing['response_id'],
            )
        );
    }

    /**
     * Configure question/configure answer
     *
     * @param $page
     * @param $config
     */
    public function configure($page, $config)
    {
        $options = json_decode($config);

        $page->setVar(
            array(
                'CONFIGURATION_MANDATORY_CHECKED' => $options->mandatory ? 'checked="checked"' : ''
            )
        );
    }

    /**
     * Configure question/configure answer during creation/modification
     *
     * @param $page
     * @param $config
     */
    public function configureXml($page, $config)
    {
        $options = json_decode($config);

        /* std objects cast as arrays -_- since json.stringify
         * compiles them into anonymous objects
         */
        $keys = array_keys((array)$options);

        if (is_array($keys) && count($keys)) {
            foreach ($keys as $currentConfigOption) {
                $page->setVar(
                    array(
                        'ANSWER_CONFIGURATION_NAME' => $currentConfigOption,
                        'ANSWER_CONFIGURATION_VALUE' => $options->$currentConfigOption
                    )
                );

                $page->parse('answer_configuration_block', 'answer_configuration', true);
            }
        } else {
            $page->setVar(
                array(
                    'answer_configuration_block' => ''
                )
            );
        }
    }

    /**
     * Configure mandatoryness and other settings for this response
     *
     * @param $page
     * @param $question_id
     */
    protected function configureResponse($page, $question_id)
    {
        $question = $this->questionHandler->getByID($question_id);
        $config = json_decode($question['config']);

        $page->setVar(
            array(
                'CONFIGURATION_MANDATORY' => $config->mandatory ? 'required' : '',
            )
        );
    }
}
