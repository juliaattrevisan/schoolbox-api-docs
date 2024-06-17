<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

/**
 * Answer type for surveys: accepts a datetime as a valid response.
 */
class Survey_Answer_DateTime extends Evaluation_Answer_DateTime
{
    /**
     * Javascript class to use when creating UI
     *
     * @var string
     */
    protected $plugin = 'evaluationDateTime';

    /**
     * @var string
     */
    protected $graphTemplateFile = 'survey/answer/dateTime/graph.html';

    /**
     * Creates an Answer. This is used when creating Questions and Answers in
     * the setup phase.
     *
     * @param array      $object
     * @param int|string $question_id
     * @return bool|int
     */
    public function create($object, $question_id = '')
    {
        $answer                = array_intersect_key($object, array_flip($this->allowedFields));
        $answer['question_id'] = $question_id;

        /* 'Short' always has only one answer per question so we can override
         * the sequence here
         */
        $answer['sequence'] = 1;

        $answer_id = parent::create($answer);

        if (!$answer_id) {
            return false;
        }

        return $answer_id;
    }

    public function prepareResponse(SitePage $page, $attemptId, $question_id)
    {
        /* Optional configuration hook
         */
        $this->configureResponse($page, $question_id);
        $existing      = $this->getResponseHandler()->isResponseExists($attemptId, $question_id);
        $currentAnswer = $this->getByQuestionID($question_id);
        if ($existing['response']) {
            $answer = $this->hydrateDate($existing['response']) ?? '';
            $page->setVar(
                [
                    'QUESTION_ID'     => $question_id,
                    'QUESTION_PLUGIN' => $this->plugin,
                    'ANSWER_ID'       => $currentAnswer['answer_id'],
                    'ANSWER'          => Scrub::text($answer),
                ]
            );
        } else {
            $page->setVar(
                [
                    'QUESTION_ID'     => $question_id,
                    'QUESTION_PLUGIN' => $this->plugin,
                    'ANSWER_ID'       => $currentAnswer['answer_id'],
                    'ANSWER'          => '',
                ]
            );
        }

        $page->parse('answerOption_block', 'answerOption', true);
    }

    /**
     * Saves a response for this Answer type. This should be customised based on
     * the format and structure of the answer (e.g. serialize multichoice)
     *
     * @param array $data
     * @return array
     */
    public function saveResponse($data)
    {
        // Convert answer to a standard date format,
        // either with or without a time component?
        $dehydratedDate = $this->dehydrateDate($data['answer'] ?? null);
        return parent::saveResponseInternal(
            $data['attempt_id'],
            $data['section_id'],
            $data['question_id'],
            $dehydratedDate,
        );
    }

    public function exportMoodleAnswer(SitePage $page, $question_id)
    {
        $currentAnswer = $this->getByQuestionID($question_id);
        $page->setVar('CORRECT_ANSWER', Scrub::text($currentAnswer['answer']));
        $page->parse('answerOption_block', 'answerOption');
    }

    public function importMoodleAnswer(SimpleXMLElement $xmlObj, $question_id)
    {
        $object = [
            'question_id' => $question_id,
            'sequence'    => 1,
            'answer'      => html_entity_decode(trim((string)$xmlObj->answer->text), ENT_IGNORE | ENT_QUOTES),
        ];

        return $this->create($object, $question_id);
    }


    /**
     * Renders answers when editing a Question. If a question_id was provided,
     * load its existing answers and populate fields; otherwise empty them or
     * set to other default values.
     *
     * @param SitePage $page
     * @param string   $question_id
     * @return void
     */
    public function modify($page, $question_id = '')
    {
        if (is_numeric($question_id)) {
            $currentAnswer = parent::getForQuestion($question_id);

            $page->setVar(
                [
                    'ANSWER_ID' => $currentAnswer['answer_id'],
                    'ANSWER'    => Scrub::text($currentAnswer['answer']),
                ]
            );
        } else {
            $page->setVar(
                [
                    'ANSWER_ID' => '',
                    'ANSWER'    => '',
                ]
            );
        }
    }

    /**
     * Clear child blocks after parsing out the parent. Keep this in its own
     * method for polymorphic algorithm
     *
     * @param SitePage $page
     * @return void
     */
    public function cleanupParse(SitePage $page)
    {
    }

    /**
     * Datetime answers aren't graphable: at least, no method has been
     * specified for graphing them.
     *
     * @param SitePage $page
     * @param array    $currentQuestion
     * @return void
     */
    public function graphResults($page, $currentQuestion)
    {
        $componentHandle = 'temp' . $currentQuestion['question_id'];
        $page->setFile($componentHandle, $this->getGraphTemplateFile());
        $page->parse('graph_block', $componentHandle);
    }

    /**
     * Get the response for a particular question
     *
     * @param int $attempt_id
     * @param int $question_id
     * @return mixed
     */
    public function exportResponseToCSV($attempt_id, $question_id)
    {
        $response = $this->getResponseHandler()->getByAttemptAndQuestion($attempt_id, $question_id);

        /* If a question was added after the attempt was made by the student,
         * then $response['response'] might not even exist
         */
        if (!$response) {
            return [
                'response' => '',
                'comment'  => '',
                'mark'     => '',
            ];
        }
        $response['response'] = $this->hydrateDate($response['response'] ?? null) ?? '';
        return $response;
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
     * @param int $attempt_id
     * @param int $question_id
     * @return mixed
     */
    public function resultsForQuestion($attempt_id, $question_id)
    {
        $data = $this->exportResponseToCSV($attempt_id, $question_id);

        $data['responses'] = $data['responsesData'] = Scrub::text($data['response'] ?? '');

        return $data;
    }

    /**
     * Show the response someone submitted and also show the correct answer.
     * Provide an option for the teacher to review the mark and adjust.
     *
     * @param SitePage $page
     * @param int      $attemptId
     * @param int      $question_id
     * @return void
     */
    public function reviewResponse($page, $attemptId, $question_id)
    {
        $existing      = $this->getResponseHandler()->isResponseExists($attemptId, $question_id);
        $answer        = $this->hydrateDate($existing['response'] ?? null) ?? '';
        $currentAnswer = $this->getByQuestionID($question_id);
        $page->setVar([
            'ANSWER_ID'   => $currentAnswer['answer_id'],
            'ANSWER'      => Scrub::text($answer),
            'RESPONSE_ID' => $existing['response_id'],
        ]);
    }

    /**
     * @inheritdoc
     */
    public function configure(SitePage $page, $config)
    {
        $options = json_decode($config);
        $page->setVar('CONFIGURATION_MANDATORY_CHECKED', ($options->mandatory ?? false) ? 'checked="checked"' : '');
    }

    /**
     * Configure mandatoryness and other settings for this response
     *
     * @param SitePage $page
     * @param int      $question_id
     */
    protected function configureResponse($page, $question_id)
    {
        $question = $this->questionHandler->getByID($question_id);
        $config   = json_decode($question['config']);
        $page->setVar('CONFIGURATION_MANDATORY', ($config->mandatory ?? false) ? 'required' : '');
    }

    /**
     * Get the response for a particular question
     *
     * @param int $attempt_id
     * @param int $question_id
     * @return mixed
     */
    public function exportResponseToArray($attempt_id, $question_id)
    {
        return $this->getResponseHandler()->getByAttemptAndQuestion($attempt_id, $question_id);
    }

    /**
     * Converts date from storage to output format.
     *
     * @param string|null $date
     * @return string|null
     */
    private function hydrateDate(?string $date): ?string
    {
        if (!isset($date)) {
            return null;
        }
        try {
            $dateObject = new Alaress_Date_Format(
                $date,
                [DateTimeInterface::RFC3339, DateTimeInterface::RFC3339_EXTENDED],
            );
        } catch (InvalidArgumentException $e) {
            return null;
        }
        return $dateObject->getLocal();
    }

    /**
     * Converts date from input to storage format.
     *
     * @param string|null $date
     * @return string|null
     */
    private function dehydrateDate(?string $date): ?string
    {
        if (!isset($date)) {
            return null;
        }
        try {
            $dateObject = Alaress_Date_Format::newLocal($date);
        } catch (InvalidArgumentException $e) {
            $dateObject = null;
        }
        return $dateObject?->toRfc3339String();
    }
}
