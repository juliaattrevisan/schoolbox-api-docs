<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Page\Quiz;

use AcceptanceTester;

/**
 * Quiz Question form page object
 */
class QuestionForm
{

    public const URL = '/assess/question/modify/{question_id}';
    // http://192.168.36.10/assess/question/create/{section_id}

    public const FORM = '#createQuestionForm';
    public const SELECTOR_TITLE = self::FORM . ' input[name="title"]';
    public const SELECTOR_BUTTON_CREATE = self::FORM . ' [type=submit][value="Add Question"]';
    public const SELECTOR_TYPE_ID = 'select[name="type_id"]';
    public const SELECTOR_MARKS = 'input[name="marks"]';
    public const SELECTOR_ANSWER = 'input[name="answer"]';

    public const TYPE_NUMERIC_ANSWER = 13;
    public const TYPE_SHORT_ANSWER = 1;
    public const TYPE_EXACT_ANSWER = 15;

    /**
     * @var AcceptanceTester
     */
    protected $tester;

    /**
     * Constructor
     *
     * @param AcceptanceTester $i
     */
    public function __construct(AcceptanceTester $i)
    {
        $this->tester = $i;
    }

    /**
     * Sets question type
     * @param int $type
     * @return QuestionForm
     * @throws \Exception
     */
    public function setType(int $type): self
    {
        $this->tester->selectOption(self::SELECTOR_TYPE_ID, $type);
        $this->tester->waitForElementVisible(self::SELECTOR_ANSWER);
        return $this;
    }

    /**
     * Sets question
     * @param string $question
     * @return QuestionForm
     */
    public function setQuestion(string $question): self
    {
        $this->tester->fillCkEditorById('question', $question);
        return $this;
    }

    /**
     * Sets mark
     * @param int $mark
     * @return QuestionForm
     */
    public function setMark(int $mark): self
    {
        $this->tester->fillField(self::SELECTOR_MARKS, $mark);
        return $this;
    }

    /**
     * Sets answer
     * @param string $answer
     * @return QuestionForm
     */
    public function setAnswer(string $answer): self
    {
        $this->tester->fillField(self::SELECTOR_ANSWER, $answer);
        return $this;
    }

    /**
     * Submits this form to create a question
     * @return QuestionForm
     * @throws \Exception
     */
    public function create(): self
    {
        $this->tester->click(self::SELECTOR_BUTTON_CREATE);
        $this->tester->waitForText('CREATE QUESTION');
        return $this;
    }

    /**
     * Gets section id of a quiz just created
     * @return mixed
     */
    public function getSectionId()
    {
        $sectionId = $this->tester->grabValueFrom(self::SELECTOR_SECTION_ID);
        return $sectionId;
    }

    /**
     * Gets question id from question type and question
     * @param int $type
     * @param string $question
     * @return mixed
     */
    public function getQuestionId(int $type, string $question)
    {
        $questionIds = $this->tester->grabColumnFromDatabase(
            'assess_question',
            'question_id',
            ['type_id' => $type, 'question like' => '%' . $question . '%']
        );
        return $questionIds[0];
    }
}
