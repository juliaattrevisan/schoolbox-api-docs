<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

use Schoolbox\Mapper\Exception;
use Schoolbox\User;
use Schoolbox\UserMapper;

/**
 * Answer type for surveys: accepts one of the current user's children as a
 * valid response.
 */
class Survey_Answer_ParentChild extends Evaluation_Answer_ParentChild
{
    /**
     * Javascript class to use when creating UI
     *
     * @var string
     */
    protected $plugin = 'evaluationRadio';

    /**
     * @var string
     */
    protected $graphTemplateFile = 'survey/answer/parentChild/graph.html';

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

    public function prepareResponse(SitePage $page, $attemptId, $question_id)
    {
        /** @var User $currentUser */
        $currentUser = $page->getDi()->get('user');

        /* Optional configuration hook
         */
        $this->configureResponse($page, $question_id);
        $page->setBlock('answerFormFragment', 'emptyAnswerState', 'emptyAnswerState_block');
        $page->setVar(
            [
                'QUESTION_ID'     => $question_id,
                'QUESTION_PLUGIN' => $this->plugin,
            ]
        );

        $existing = $this->getResponseHandler()->isResponseExists($attemptId, $question_id);
        $children = $currentUser->getChildren() ?? [];
        if (count($children) > 0) {
            foreach ($children as $child) {
                $page->setVar(
                    [
                        'QUESTION_ID'        => $question_id,
                        'QUESTION_PLUGIN'    => $this->plugin,
                        'ANSWER_VALUE'       => $child->getId(),
                        'ANSWER_DESCRIPTION' => Scrub::text($child->getFullName()),
                    ]
                );

                $isChecked = false;
                if ($existing['response'] && $child->getId() === (int)($existing['response'])) {
                    $isChecked = true;
                }
                $page->setVar('ANSWER_CHECKED', $isChecked ? 'checked=\'checked\'' : '');
                $page->parse('answerOption_block', 'answerOption', true);
            }
            $page->setVar('emptyAnswerState_block', '');
        } else {
            $page->setVar('answerOption_block', '');
            $page->parse('emptyAnswerState_block', 'emptyAnswerState');
        }
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
        return parent::saveResponseInternal(
            $data['attempt_id'],
            $data['section_id'],
            $data['question_id'],
            $data['answer'] ?? null,
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
        $object = array(
            'question_id' => $question_id,
            'sequence' => 1,
            'answer' => html_entity_decode(trim((string)$xmlObj->answer->text), ENT_IGNORE | ENT_QUOTES),
        );

        return $this->create($object, $question_id);
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
     * Graph the results for this answer type
     *
     * @param SitePage $page
     * @param array $currentQuestion
     * @return void
     */
    public function graphResults($page, $currentQuestion)
    {
        $graph = new Survey_Graph_Render($page, $currentQuestion, $this->getGraphTemplateFile());
        $graph->graph();
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
        $data     = [
            'response' => '',
            'comment'  => '',
            'mark'     => 'No User Selected'
        ];

        /* If a question was added after the attempt was made by the student,
         * then $response['response'] might not even exist
         */
        if (!$response) {
            return $data;
        }

        $userId = filter_var($response['response'] ?? null, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);
        if ($userId) {
            $student = $this->loadStudent($userId);
            // Show the student and their external ID
            $responseText = $student->getFullName();
            if ($student->getExternalId()) {
                $responseText = sprintf('%s (%s)', $responseText, $student->getExternalId());
            }
            $data['response'] = $responseText;
        }
        return $data;
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
     * @param int $attemptId
     * @param int $question_id
     * @return void
     */
    public function reviewResponse($page, $attemptId, $question_id)
    {
        // Need the user which took the survey, not the current user?
        /** @var User $currentUser */
        $currentUser = $page->getDi()->get('user');
        $children    = $currentUser->getChildren() ?? [];
        $existing    = $this->getResponseHandler()->isResponseExists($attemptId, $question_id);
        if (count($children) > 0) {
            $page->setVar(
                [
                    'RESPONSE_ID'      => $existing['response_id'],
                    'REVIEWED_COMMENT' => Scrub::text($existing['comment']),
                    'REVIEWED_MARKS'   => $existing['mark'],
                ]
            );

            foreach ($children as $child) {
                $page->setVar(
                    [
                        'ACTUAL_CORRECT_VALUE' => '',
                        'ANSWER_VALUE'         => $child->getId(),
                        'ANSWER_DESCRIPTION'   => Scrub::text($child->getFullName()),
                    ]
                );

                $isChecked = false;
                if ($existing['response'] && $child->getId() === (int)($existing['response'])) {
                    $isChecked = true;
                }
                $page->setVar(
                    [
                        'ANSWER_OPTION_STATUS' => $isChecked ? 'evaluation-selected-answer-option' : '',
                        'ANSWER_CHECKED'       => $isChecked ? 'checked' : '',
                    ]
                );
                $page->parse('answerOption_block', 'answerOption', true);
            }
        } else {
            $page->setVar('answerOption_block', '');
        }
    }

    /**
     * Load a user from the given student ID.
     *
     * @param int $studentId
     * @return User
     */
    private function loadStudent(int $studentId): User
    {
        $userMapper = UserMapper::getInstance();
        try {
            $student = $userMapper->findById($studentId);
        } catch (Exception\Deleted $e) {
            /** @var User $student */
            $student = $e->getDeletedObject();
        } catch (Exception) {
            $student = $userMapper->getUnknownUser();
        }
        return $student;
    }
}
