<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Page\Survey;

use AcceptanceTester;

/**
 * Survey Question form page object
 */
class QuestionForm
{

    public const URL = '/assess/question/modify/{question_id}';
    // http://192.168.36.10/assess/question/create/{section_id}

    public const FORM = '#createQuestionForm';
    public const SELECTOR_TITLE = self::FORM . ' input[name="title"]';
    public const SELECTOR_BUTTON_CREATE = self::FORM . ' [type=submit][value="Add Question"]';
    public const SELECTOR_BUTTON_MODIFY = self::FORM . ' [type=submit][value="Modify"]';
    public const SELECTOR_TYPE_ID = self::FORM . ' select[name="type_id"]';
    public const SELECTOR_SECTION_ID = 'input[name="section_id"]';
    public const SELECTOR_DESCRIPTION = '#cke_question';

    public const TYPE_YESNO = 10;
    public const TYPE_SHORT_SIGNATURE = 16;
    public const TYPE_SHORT_ANSWER = 19;

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
        $this->tester->waitForElementVisible(self::SELECTOR_DESCRIPTION);
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
     * Submits this form to create a question
     * @return QuestionForm
     * @throws \Exception
     */
    public function create(): self
    {
        $i = $this->tester;
        //keeping this part off for a while to see if it will impact this test or any other tests
        //$i->scrollTo(['css' => self::SELECTOR_BUTTON_CREATE]);
        //$i->waitForElementVisible(['css' => self::SELECTOR_BUTTON_CREATE],5);
        //$this->tester->click(self::SELECTOR_BUTTON_CREATE);
        $i->scrollAndClick(['css' => self::SELECTOR_BUTTON_CREATE]);
        $this->tester->waitForText('CREATE QUESTION');
        return $this;
    }

    /**
     * For Question Bank Only for now...
     * @throws \Exception
     */
    public function modify()
    {
        $i = $this->tester;
        //keeping this part off for a while to see if it will impact this test or any other tests
        //$i->scrollTo(['css' => self::SELECTOR_BUTTON_MODIFY]);
        //$i->waitForElementVisible(['css' => self::SELECTOR_BUTTON_MODIFY],5);
        //$i->click(self::SELECTOR_BUTTON_MODIFY);
        $i->scrollAndClick(['css' => self::SELECTOR_BUTTON_MODIFY]);
        // TODO: make it more general
        $i->waitForText('Question Bank');
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
     * Gets question id from question type and section id
     * @param int $type
     * @param int $sectionId
     * @return mixed
     */
    public function getQuestionId(int $type, int $sectionId)
    {
        $questionIds = $this->tester->grabColumnFromDatabase(
            'assess_question',
            'question_id',
            ['type_id' => $type, 'section_id' => $sectionId]
        );
        return $questionIds[0];
    }
}
