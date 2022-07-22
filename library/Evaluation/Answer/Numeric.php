<?php

/**
 * Evaluation_Answer_Numeric
 *
 * Handler for Short Answer questions.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
abstract class Evaluation_Answer_Numeric extends Evaluation_Answer
{

    /**
     * Javascript class to use when creating UI
     *
     * @var string
     */
    protected $plugin = 'evaluationNumeric';

    /**
     * Creates an Answer. This is used when creating Questions and Answers in
     * the setup phase.
     *
     * @param array $object
     * @param int|string $question_id
     * @return bool|int
     */
    public function create($object, $question_id = '')
    {
        $answer = array_intersect_key($object, array_flip($this->allowedFields));
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

    /**
     * Updates an existing Answer.
     *
     * @param array $object
     * @return void
     */
    public function update($object)
    {
        $answer = array_intersect_key($object, array_flip($this->allowedFields));

        parent::update($answer);
    }

    /**
     * Renders answers when editing a Question. If a question_id was provided,
     * load its existing answers and populate fields; otherwise empty them or
     * set to other default values.
     *
     * @param SitePage $page
     * @param string $question_id
     * @return void
     */
    public function modify($page, $question_id = '')
    {
        if (is_numeric($question_id)) {
            $currentAnswer = parent::getForQuestion($question_id);

            $page->setVar(
                array(
                    'ANSWER_ID' => $currentAnswer['answer_id'],
                    'ANSWER' => Scrub::text($currentAnswer['answer']),
                )
            );
        } else {
            $page->setVar(
                array(
                    'ANSWER_ID' => '',
                    'ANSWER' => '',
                )
            );
        }
    }

    /**
     * Render answers in the QuestionBank
     *
     * @param SitePage $page
     * @param string $answer
     * @return void
     */
    public function modifyInQuestionBank($page, $answer)
    {
        $page->setVar(array(
            'ANSWER' => Scrub::text($answer),
        ));
    }

    /**
     * Serialize for db storage (we shouldn't be using serialize anymore)
     *
     * @param string $data
     * @return string
     */
    public function encodeForQuestionBank($data)
    {
        return $data;
    }

    /**
     * Resets answer tokens so a user can fill them in while actually taking
     * the test.
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
                    'ANSWER' => Scrub::text($existing['response']),
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
     * Clear child blocks after parsing out the parent. Keep this in its own
     * method for polymorphic algorithm
     *
     * @param SitePage $page
     * @return void
     */
    public function cleanupParse($page)
    {
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
        return parent::saveResponseInternal($data['attempt_id'], $data['section_id'], $data['question_id'], $data['answer']);
    }

    /**
     * Exports the answer to Moodle XML format. This is a modified version of
     * $this->reviewResponse()
     *
     * @param SitePage $page
     * @param int $question_id
     * @return void
     */
    public function exportMoodleAnswer(SitePage $page, $question_id)
    {
        $currentAnswer = $this->getByQuestionID($question_id);
        $page->setVar('CORRECT_ANSWER', Scrub::text($currentAnswer['answer']));
        $page->parse('answerOption_block', 'answerOption');
    }

    /**
     * Imports form a Moodle XML object. This is largely an alias of create()
     * but we create the array specially because of the naming conventions and
     * structure of the original xml export.
     *
     * @param  SimpleXMLElement$xmlObj
     * @param  int $question_id
     * @return bool|int
     */
    public function importMoodleAnswer(SimpleXMLElement $xmlObj, $question_id)
    {
        $object = array(
            'question_id' => $question_id,
            'sequence' => 1,
            'answer' => trim((string)$xmlObj->answer->text),
        );

        return $this->create($object, $question_id);
    }

    /**
     * Get the response for a particular question
     *
     * @param int $attempt_id
     * @param int $question_id
     *
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
                'response' => '',
                'comment' => '',
                'mark' => ''
            ];
        }

        /* Remove tab characters
         */
        $data['response'] = str_replace("\t", '', $response['response']);

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
     *
     * @return mixed
     */
    public function exportResponseToArray($attempt_id, $question_id)
    {
        $response = $this->getResponseHandler()->getByAttemptAndQuestion($attempt_id, $question_id);

        /* If a question was added after the attempt was made by the student,
         * then $response['response'] might not even exist; return empty array
         */
        return $response['response'] ?? [];
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

        $isCorrect = ($existing['response'] == $currentAnswer['answer']);

        if ($isCorrect) {
            $title = 'Respondent provided a correct answer';
        } else {
            $title = 'Respondent did not provide a correct answer';
        }

        /* Removed this check because it was wrongly matching a blank saved
         * response with no response at all, preventing reviewed marks from
         * displaying properly.
         *
         * if ($existing['response'] != null) { ... } else { ... }
         */
        $page->setVar(
            array(
                'ANSWER_ID' => $currentAnswer['answer_id'],
                'ANSWER' => Scrub::text($existing['response']),
                'ANSWER_OPTION_STATUS' => $isCorrect
                    ? 'evaluation-correct-answer-option'
                    : 'evaluation-incorrect-answer-option',
                'ANSWER_ICON_CLASS' => $isCorrect
                    ? 'evaluation-correct-answer-icon'
                    : 'evaluation-incorrect-answer-icon',
                'ANSWER_ICON_TITLE' => $title,
                'RESPONSE_ID' => $existing['response_id'],
                'REVIEWED_COMMENT' => Scrub::text($existing['comment']),
                'REVIEWED_MARKS' => $existing['mark'],
            )
        );

        /* Show correct answer
           */
        $page->setVar(
            array(
                'CORRECT_ANSWER' => Scrub::text($currentAnswer['answer'])
            )
        );

        $page->parse('answerOption_block', 'answerOption', true);
    }

    /**
     * Show the response someone submitted and also show the correct answer.
     * Provide an option for the teacher to review the mark and adjust.
     *
     * @param SitePage $page
     * @param int $attemptId
     * @param array $currentQuestion
     */
    public function reviewResponseHorizontal($page, $attemptId, $currentQuestion)
    {
        $existing = $this->getResponseHandler()->isResponseExists($attemptId, $currentQuestion['question_id']);

        $currentAnswer = $this->getByQuestionID($currentQuestion['question_id']);

        $isCorrect = ($existing['response'] == $currentAnswer['answer']);

        if ($isCorrect) {
            $title = 'Respondent provided a correct answer';
        } else {
            $title = 'Respondent did not provide a correct answer';
        }

        /* Removed this check because it was wrongly matching a blank saved
         * response with no response at all, preventing reviewed marks from
         * displaying properly.
         *
         * if ($existing['response'] != null) { ... } else { ... }
         */
        $page->setVar(array(
            'RESPONSE_USER_ID' => $currentQuestion['user_id'],
            'USER_FIRSTNAME' => $currentQuestion['firstname'],
            'USER_LASTNAME' => $currentQuestion['lastname'],
            'ANSWER_ID' => $currentAnswer['answer_id'],
            'ANSWER' => Scrub::text($existing['response']),
            'ANSWER_CORRECT' => $isCorrect ? 'multiCorrect' : 'multiIncorrect',
            'ANSWER_OPTION_STATUS' => $isCorrect
                ? 'evaluation-correct-answer-option'
                : 'evaluation-incorrect-answer-option',
            'ANSWER_ICON_CLASS' => $isCorrect
                ? 'evaluation-correct-answer-icon'
                : 'evaluation-incorrect-answer-icon',
            'ANSWER_ICON_TITLE' => $title,
            'RESPONSE_ID' => $existing['response_id'],
            'REVIEWED_COMMENT' => Scrub::text($existing['comment']),
            'REVIEWED_MARKS' => $existing['mark'],
        ));

        /* Show correct answer
           */
        $page->setVar(array(
            'CORRECT_ANSWER' => Scrub::text($currentAnswer['answer'])
        ));

        $page->parse('answerOption_block', 'answerOption', true);
    }

    /**
     * The answer for a question during horizontal marking, so it appears at the
     * top under the question itself, and not repeated for each attempt
     *
     * @param SitePage $page
     * @param array $currentQuestion
     * @return void
     */
    public function reviewResponseHorizontalAnswerForQuestion($page, $currentQuestion)
    {
        $currentAnswer = $this->getByQuestionID($currentQuestion['question_id']);

        /* Show correct answer
         */
        $page->setVar(array(
            'CORRECT_ANSWER' => Scrub::text($currentAnswer['answer'])
        ));

        $page->parse('answerTop_block', 'answerTop', true);
    }
}
