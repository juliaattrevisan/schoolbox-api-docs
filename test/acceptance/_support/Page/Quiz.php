<?php
/**
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Page;

use Codeception\Util\Locator;
use Page\Common;

class Quiz
{
    public const URL_CREATE_DUEWORK = '/learning/folders/{class_id}/assessments/new?type=quiz';
    public const URL_CLASS_ID = '{class_id}';

    public const TYPE_NUMERIC_ANSWER = 13;
    public const TYPE_SHORT_ANSWER = 1;
    public const TYPE_EXACT_ANSWER = 15;

    // create quiz form
    public const ASSESSMENT_FORM = '#assessmentForm';
    public const SELECTOR_TITLE = self::ASSESSMENT_FORM . ' input[name="title"]';
    public const SELECTOR_WORKTYPE = self::ASSESSMENT_FORM . ' select[name="workType"]';
    public const SELECTOR_MAX_ATTEMPTS = self::ASSESSMENT_FORM . ' select[name="maxAttempts"]';
    public const SELECTOR_PUBLISH_DATE = self::ASSESSMENT_FORM . ' input[name="publishDate"]';
    public const SELECTOR_OPEN_DATE = self::ASSESSMENT_FORM . ' input[name="openDate"]';
    public const SELECTOR_DUE_DATE = self::ASSESSMENT_FORM . ' input[name="dueDate"]';
    public const SELECTOR_BUTTON_CREATE = self::ASSESSMENT_FORM . ' [type=submit][value="Create"]';

    // create question form
    public const QUESTION_FORM = '#createQuestionForm';

//    // status page
//    public const SELECTOR_MARK = 'input[name="mark"]';
//    public const SELECTOR_COMMENT = 'textarea[name="comment"]';
//    public const SELECTOR_BUTTON_PUBLISH = 'a[data-draft="publish"]';
//
    // option values
    public const WORKTYPE_ASSESSMENT_TASK = 1;
    public const WORKTYPE_ASSIGNMENT = 2;
    public const WORKTYPE_HOMEWORK = 3;
//    public const ACCEPT_SUBMISSION_NONE = 0;
//    public const ACCEPT_SUBMISSION_SINGLE = 1;
//    public const ACCEPT_SUBMISSION_MULTIPLE = 2;

    /**
     * @var AcceptanceTester
     */
    protected $tester;

    public $className;
    public $classId;

    public function __construct(\AcceptanceTester $i)
    {
        $this->tester = $i;
    }

    /**
     * Gets url to create a quiz
     * @param int $classId
     * @return string the URL
     */
    public static function getCreateQuizUrl(int $classId): string
    {
        return str_replace(
            self::URL_CLASS_ID, $classId, self::URL_CREATE_DUEWORK
        );
    }

    public static function getStatusSelector(int $studentId, String $fieldSelector) {

        return '#assessment-status li[data-participant="' . $studentId . '"] ' . $fieldSelector;
    }


        /**
     * Verifies that the notification exists
     * @param $notif
     */
    public function assertNotifExists($notif)
    {
        $i = $this->tester;

        $i->amOnPage('/messages');
        $i->see($notif);
    }

    /**
     * @param array $selects
     * @param array $texts
     * @return mixed
     * @throws \Exception
     */
    public function createQuiz(array $selects, array $texts)
    {
        $i = $this->tester;

        $i->amOnPage(Quiz::getCreateQuizUrl($this->classId));
        foreach ($selects as $fieldName => $fieldValue) {
            $i->selectOption($fieldName, $fieldValue);
        }
        foreach ($texts as $fieldName => $fieldValue) {
            $i->fillField($fieldName, $fieldValue);
        }

        $i->click(Quiz::SELECTOR_BUTTON_CREATE);
        $i->waitForElement('input[name="assess_id"]');
        $assessId = $i->grabValueFrom('input[name="assess_id"]');
        return $assessId;
    }

    /**
     * @param String $title
     * @return mixed
     * @throws \Exception
     */
    public function createQuizSection(String $title)
    {
        $i = $this->tester;

        $i->fillField('input[name="title"]', $title);
        $i->click('#createSection [type=submit][value="Create Section"]');
        $i->waitForElement('input[name="section_id"]');
        $sectionId = $i->grabValueFrom('input[name="section_id"]');
        return $sectionId;
    }

    /**
     * @param int $type
     * @param String $question
     * @param int $mark
     * @param String $answer
     * @return mixed
     * @throws \Exception
     */
    public function createQuestion(int $type, String $question, int $mark, String $answer)
    {
        $i = $this->tester;

        $i->selectOption(self::QUESTION_FORM . ' select[name=type_id]', $type);
        $i->fillCkEditorById('question', $question);
        $i->fillField(self::QUESTION_FORM . ' input[name=marks]', $mark);
        $i->fillField(self::QUESTION_FORM . ' input[name="answer"]', $answer);

        $i->click(self::QUESTION_FORM . ' [type=submit][value="Add Question"]');
        $i->waitForText('CREATE QUESTION');

        // get question_id and section_id just created
        $questionIds = $i->grabColumnFromDatabase('assess_question', 'question_id',
            ['type_id' => $type, 'question like' => '%' . $question . '%']);

        return $questionIds[0];
    }

    /**
     * @param String $quizTitle
     * @param String $contents
     * @return int
     * @throws \Exception
     */
    public function createAttempt(String $quizTitle, String $contents)
    {
        $i = $this->tester;
        $i->click($quizTitle, '#content');
        $i->waitForText('Begin Quiz');
        $i->click('Begin Quiz', '#content');
        $i->waitForText($quizTitle);
        $i->fillField('form input[name="answer"]', $contents);
        $i->click('Finish');
        $i->click('Yes');
        $i->waitForText('Quiz Completed');
        // http://192.168.36.10/assess/end/60/69
        $endUrl = $i->grabFromCurrentUrl();
        $pos = strrpos($endUrl, '/');
        $attemptId = intval(substr($endUrl, $pos + 1));
        return $attemptId;
    }

}
