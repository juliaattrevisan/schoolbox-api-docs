<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

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
    protected $plugin = 'parentChild';

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
        /** @var \Schoolbox\User $currentUser */
        $currentUser = $page->getDi()->get('user');

        /* Optional configuration hook
         */
        $this->configureResponse($page, $question_id);

//        $questionConfig = json_decode($this->questionHandler->getByID($question_id)['config']);
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

//                if ($existing['response']) {
//                    $existingAnswer = unserialize($existing['response']);
//                    if (is_array($existingAnswer) && in_array($currentAnswer->value, $existingAnswer)) {
//                        $page->setVar(
//                            array(
//                                'ANSWER_CHECKED' => 'checked=\'checked\'',
//                            )
//                        );
//                    } else {
//                        $page->setVar(
//                            array(
//                                'ANSWER_CHECKED' => '',
//                            )
//                        );
//                    }
//                } else {
//                    $page->setVar(
//                        array(
//                            'ANSWER_CHECKED' => '',
//                        )
//                    );
//                }
//
                $page->parse('answerOption_block', 'answerOption', true);
//
//                $i++;
            }
        } else {
            $page->setVar('answerOption_block', '');
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
        return parent::saveResponseInternal($data['attempt_id'], $data['section_id'], $data['question_id'], $data['answer']);
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
}
