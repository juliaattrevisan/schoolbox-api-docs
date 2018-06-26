<?php
/**
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Page;

use Codeception\Util\Locator;

class Survey
{
    public const URL_CREATE_SURVEY = '/survey/create/{class_id}';
    public const URL_SUMMARY_SURVEY = '/survey/summary/{assess_id}';
    public const URL_CLASS_ID = '{class_id}';
    public const URL_ASSESS_ID = '{assess_id}';

    public const TYPE_YESNO = 10;
    public const TYPE_SHORT_SIGNATURE = 16;
    public const TYPE_SHORT_ANSWER = 19;
    /**
     * @var AcceptanceTester
     */
    protected $tester;

    public function __construct(\AcceptanceTester $i)
    {
        $this->tester = $i;
    }

    /**
     * Returns url to create a survey
     * @param int $classId
     * @return mixed
     */
    public static function getCreateSurveyUrl(int $classId)
    {
        // http://192.168.36.10/survey/create/176
        return str_replace(self::URL_CLASS_ID, $classId, self::URL_CREATE_SURVEY);
    }

    /**
     * Returns url to modify a survey
     * @param int $assessId
     * @return mixed
     */
    public static function getSummarySurveyUrl(int $assessId)
    {
        // http://192.168.36.10/survey/summary/176
        return str_replace(self::URL_ASSESS_ID, $assessId, self::URL_SUMMARY_SURVEY);
    }

    /**
     * Creates a question step by step
     * @param int $type
     * @param String $question
     * @return mixed
     */
    public function createQuestion(int $type, String $question)
    {
        $i = $this->tester;

        $i->selectOption('form select[name=type_id]', $type);
        $i->fillCkEditorById('question', $question);
        $i->click('#createQuestionForm [type=submit][value="Add Question"]');
        $i->waitForText('CREATE QUESTION');

        // get question_id and section_id just created
        $questionIds = $i->grabColumnFromDatabase('assess_question', 'question_id',
            ['type_id' => $type, 'question like' => '%' . $question . '%']);

        return $questionIds[0];
    }

    /**
     * Adds a question to question bank
     * @param int $questionId
     */
    public function addToQuestionBank(int $questionId)
    {
        $i = $this->tester;

        $i->executeJs('$("#question_' . $questionId . ' a.icon-download").click()');
        $i->waitForElementVisible('#question-bank');
        // Select Question Category
        $i->executeJS('$("#node_7_anchor").click()');
        $i->click('input[type=submit][value="Add To Question Bank"]');
        $i->waitForText('Question added to the Questionbank');
        $i->reloadPage();
    }

    /**
     * Finds a question in question bank
     * @param String $question
     * @return int
     */
    public function findInQuestionBank(String $question)
    {
        $i = $this->tester;

        // Jerry Tot!
        $i->click(Locator::elementAt('#content a.icon-more', 2));
        // Add from Question Bank
        $i->click(Locator::elementAt('#content a.icon-download', 1));

        $i->waitForText('CATEGORIES');
        // Select Question Category
        $i->executeJS('$("#node_7_anchor").click()');
        $i->waitForText('ELECTIVE SELECTION');

        $numQuestionBank = $i->grabNumRecords('assess_question_bank');
        for ($j = 1; $j <= $numQuestionBank + 1; $j++) {
            $q = $i->grabTextFrom('#questionBankForm > section > ul > li:nth-child(' . $j . ')');
            if (strpos($q, $question) !== false) {
                break;
            }
        }
        return $j;
    }

    /**
     * Starts modifying a question in question bank
     * @param int $index
     * @throws \Exception
     */
    public function modifyInQuestionBank(int $index)
    {
        $i = $this->tester;

        $i->click('#questionBankForm > section > ul > li:nth-child(' . ($index) . ') > nav > a.icon-modify');
        $i->waitForElement('#createQuestionForm input[type=submit][value="Modify"]');
        $i->click('#createQuestionForm input[type=submit][value="Modify"]');
        $i->waitForText('Question Bank');

    }
}