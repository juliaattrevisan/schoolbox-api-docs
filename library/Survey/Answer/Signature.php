<?php

/**
 * Survey_Answer_Short
 *
 * Handler for Short Answer questions.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
class Survey_Answer_Signature extends Evaluation_Answer_Signature
{

    /**
     * @var string
     */
    protected $graphTemplateFile = 'survey/answer/signature/graph.html';


    /**
     * Use this to toggle between letters and actual answers
     *
     * @var bool
     */
    protected $useLongAnswers = true;


    /**
     * Graph the results for this answer type
     *
     * @param SitePage $page
     * @param array $currentQuestion
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
     * @param int $attempt_id
     * @param int $question_id
     * @return array
     */
    public function resultsForQuestion($attempt_id, $question_id)
    {
        $response = $this->getResponseHandler()->getByAttemptAndQuestion($attempt_id, $question_id);

        // Instead of using a SitePage instance to render the signature:
        // just load the template's contents
        $template = file_get_contents(SITE_root . '/templates/core/survey/answer/signature/rendered.html');

        $data['responses'] = strtr(
            $template,
            [
                 '{ID}'       => $attempt_id,
                 '{RESPONSE}' => $this->makeResponseValid($response['response'])
            ]
        );

        // Signatures are never duplicated
        $data['responsesData'] = $attempt_id;

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
     * @param int $attempt_id
     * @param int $question_id
     * @return array
     */
    public function exportResponseToCSV($attempt_id, $question_id)
    {
        $response = $this->getResponseHandler()->getByAttemptAndQuestion($attempt_id, $question_id);

        /* If a question was added after the attempt was made by the student,
         * then $response['response'] might not even exist
         */
        if (!$response) {
            return [
                'response' => 'Unsigned',
                'comment' => '',
                'mark' => ''
            ];
        }

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
     * @inheritdoc
     */
    public function prepareResponse(SitePage $page, $attemptId, $question_id)
    {
        /* Optional configuration hook
         */
        $this->configureResponse($page, $question_id);

        $existing = $this->getResponseHandler()->isResponseExists($attemptId, $question_id);

        $currentAnswer = $this->getByQuestionID($question_id);

        if ($existing['response']) {
            $page->setVar(
                array(
                    'QUESTION_ID' => $question_id,
                    'QUESTION_PLUGIN' => $this->plugin,
                    'ANSWER_ID' => $currentAnswer['answer_id'],
                    'ANSWER' => $this->makeResponseValid($existing['response']),
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
     * @param SitePage $page
     * @param int $attemptId
     * @param int $question_id
     * @return void
     */
    public function reviewResponse($page, $attemptId, $question_id)
    {
        $existing = $this->getResponseHandler()->isResponseExists($attemptId, $question_id);

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
     * @inheritdoc
     */
    public function configure(SitePage $page, $config)
    {
        $options = json_decode($config);

        $page->setVar(
            array(
                'CONFIGURATION_MANDATORY_CHECKED' => ($options->mandatory ?? false) ? 'checked="checked"' : ''
            )
        );
    }


    /**
     * Configure question/configure answer during creation/modification
     *
     * @param SitePage $page
     * @param string $config
     */
    public function configureXml(SitePage $page, $config)
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
     * @param SitePage $page
     * @param int $question_id
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

    /**
     * Ensures that $response is either valid JSON or an empty string.
     *
     * @param string $response
     * @return string
     */
    protected function makeResponseValid($response)
    {
        // Response might not fit as a JSON string
        if ($response) {
            if (!json_decode($response)) {
                return '';
//                // keep trying until it's valid
//                while (strlen($response) > 0) {
//                    $response = substr($response, 0, -1);
//                    if (json_decode($response.']')) {
//                        return $response . ']';
//                    }
//                }
            }
        }
        return $response;
    }
}
