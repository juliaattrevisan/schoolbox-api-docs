<?php
/**
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Page;

use Codeception\Util\Locator;
use Page\Common;

class Duework
{
    public const URL_CREATE_DUEWORK = '/learning/folders/{class_id}/assessments/new?type=dueWork';
    public const URL_CLASS_ID = '{class_id}';

    // create form
    public const FORM = '#assessmentForm';
    public const SELECTOR_TITLE = self::FORM . ' input[name="title"]';
    public const SELECTOR_WORKTYPE = self::FORM . ' select[name="workType"]';
    public const SELECTOR_ACCEPT_SUBMISSION = self::FORM . ' select[name="acceptOnline"]';
    public const SELECTOR_PUBLISH_DATE = self::FORM . ' input[name="publishDate"]';
    public const SELECTOR_DUE_DATE = self::FORM . ' input[name="dueDate"]';
    public const SELECTOR_BUTTON_CREATE = self::FORM . ' [type=submit][value="Create"]';
    public const SELECTOR_BUTTON_SUBMISSION = '#editor-form [type=submit][value="Submit for assessment"]';

    // status page
    public const SELECTOR_MARK = 'input[name="mark"]';
    public const SELECTOR_COMMENT = 'textarea[name="comment"]';
    public const SELECTOR_BUTTON_PUBLISH = 'a[data-draft="publish"]';

    // option values
    public const WORKTYPE_ASSESSMENT_TASK = 1;
    public const WORKTYPE_ASSIGNMENT = 2;
    public const WORKTYPE_HOMEWORK = 3;
    public const ACCEPT_SUBMISSION_NONE = 0;
    public const ACCEPT_SUBMISSION_SINGLE = 1;
    public const ACCEPT_SUBMISSION_MULTIPLE = 2;

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
     * Gets url to create a duework
     * @param int $classId
     * @return string the URL
     */
    public static function getCreateDueworkUrl(int $classId): string
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
     * @throws \Exception
     */
    public function createDuework(array $selects, array $texts)
    {
        $i = $this->tester;

        $i->amOnPage(Duework::getCreateDueworkUrl($this->classId));
        foreach ($selects as $fieldName => $fieldValue) {
            $i->selectOption($fieldName, $fieldValue);
        }
        foreach ($texts as $fieldName => $fieldValue) {
            $i->fillField($fieldName, $fieldValue);
        }

        $i->click(Duework::SELECTOR_BUTTON_CREATE);
        $i->waitForText($this->className);

    }

    /**
     * @param String $dueworkTitle
     * @param String $contents
     * @throws \Exception
     */
    public function createSubmission(String $dueworkTitle, String $contents)
    {
        $i = $this->tester;
        $i->click($dueworkTitle, '#content');
        $i->waitForText('Create submission');
        $i->click('Create submission', '#content');
        $i->waitForText($dueworkTitle);
        $i->fillCkEditorById('editor-body', $contents);
        $i->click(Duework::SELECTOR_BUTTON_SUBMISSION);
        $i->waitForText('SUBMISSION HISTORY');
    }

}
