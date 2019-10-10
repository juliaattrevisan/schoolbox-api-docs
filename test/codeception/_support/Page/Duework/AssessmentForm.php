<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Page\Duework;

use AcceptanceTester;
use Alaress_Date_Format;
use Schoolbox\Assessment\Component\SubmissionBox;

/**
 * Duework form page object
 */
class AssessmentForm
{
    public const URL = '/learning/folders/{class_id}/assessments/new?type=dueWork';

    public const FORM = '#assessmentForm';

    // Not sure if every input should have its own selector constant
    public const SELECTOR_TITLE = self::FORM . ' input[name="title"]';
    public const SELECTOR_WORKTYPE = self::FORM . ' select[name="workType"]';
    public const SELECTOR_VISIBILITY = self::FORM . ' select[name="visibility"]';
    public const SELECTOR_ACCEPT_SUBMISSION = self::FORM . ' select[name="acceptOnline"]';
    public const SELECTOR_PUBLISH_DATE = self::FORM . ' input[name="publishDate"]';
    public const SELECTOR_OPEN_DATE = self::FORM . ' input[name="openDate"]';
    public const SELECTOR_DUE_DATE = self::FORM . ' input[name="dueDate"]';
    public const SELECTOR_BUTTON_CREATE = self::FORM . ' [type=submit][value="Create"]';
    public const SELECTOR_BUTTON_UPDATE = self::FORM . ' [type=submit][value="Update"]';

    // Work type
    public const WORKTYPE_ASSESSMENT_TASK = 1;
    public const WORKTYPE_ASSIGNMENT = 2;
    public const WORKTYPE_HOMEWORK = 3;

    // Visibility
    public const VISIBILITY_STAFF_ONLY = SubmissionBox::MARK_VISIBILITY_STAFF_ONLY;
    public const VISIBILITY_STAFF_AND_STUDENT = SubmissionBox::MARK_VISIBILITY_STAFF_AND_STUDENT;
    public const VISIBILITY_ALL = SubmissionBox::MARK_VISIBILITY_STAFF_STUDENT_PARENT;

    // Accepts Online Submission
    public const ACCEPT_SUBMISSION_NONE = 0;
    public const ACCEPT_SUBMISSION_SINGLE = 1;
    public const ACCEPT_SUBMISSION_MULTIPLE = 2;

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
     * Generates route for this page.
     *
     * @param string $classId
     * @return string
     */
    public function generateRoute(string $classId): string
    {
        return str_replace('{class_id}', $classId, self::URL);
    }

    /**
     * Navigates to create duework form
     * @param string $classId
     * @return AssessmentForm
     */
    public function openCreate(string $classId): self
    {
        $i = $this->tester;
        $i->amOnPage(self::generateRoute($classId));
        return $this;
    }

    /**
     * Submits this form to create a duework
     * @return AssessmentForm
     * @throws \Exception
     */
    public function create(): self
    {
        $i = $this->tester;
        $i->waitForElement(self::SELECTOR_BUTTON_CREATE);
        $i->click(self::SELECTOR_BUTTON_CREATE);
        $i->waitForElement('#tabs-homepage');
        return $this;
    }

    /**
     * Submits this form to update a duework
     * @return AssessmentForm
     */
    public function update(): self
    {
        $this->tester->click(self::SELECTOR_BUTTON_UPDATE);
        return $this;
    }

    /**
     * Sets work type
     * @param int $workType
     * @return AssessmentForm
     */
    public function setWorkType(int $workType): self
    {
        $this->tester->selectOption(self::SELECTOR_WORKTYPE, $workType);
        return $this;
    }

    /**
     * Sets visibility
     * @param int $visibility
     * @return AssessmentForm
     */
    public function setVisibility(int $visibility): self
    {
        $this->tester->selectOption(self::SELECTOR_VISIBILITY, $visibility);
        return $this;
    }

    /**
     * Sets accepts online submissions
     * @param int $acceptSubmission
     * @return AssessmentForm
     */
    public function setAcceptSubmission(int $acceptSubmission): self
    {
        $this->tester->selectOption(self::SELECTOR_ACCEPT_SUBMISSION, $acceptSubmission);
        return $this;
    }

    /**
     * Sets title
     * @param string $title
     * @return AssessmentForm
     */
    public function setTitle(string $title): self
    {
        $this->tester->fillField(self::SELECTOR_TITLE, $title);
        return $this;
    }

    /**
     * Sets publish date
     * @param Alaress_Date_Format $publishDate
     * @return AssessmentForm
     */
    public function setPublishDate(Alaress_Date_Format $publishDate): self
    {
        // Type in database format, let strtotime do the work
        // This is because Codeception has no access to the DB config
        $this->tester->fillDateField(self::SELECTOR_PUBLISH_DATE, $publishDate->getDatabase());
        return $this;
    }

    /**
     * Sets open date
     * @param Alaress_Date_Format $openDate
     * @return AssessmentForm
     */
    public function setOpenDate(Alaress_Date_Format $openDate): self
    {
        // Type in database format, let strtotime do the work
        // This is because Codeception has no access to the DB config
        $this->tester->fillDateField(self::SELECTOR_OPEN_DATE, $openDate->getDatabase());
        return $this;
    }

    /**
     * Excludes an user from participants
     * @param $userId
     * @param $confirm
     * @param $openSection
     * @return AssessmentForm
     * @throws \Exception
     */
    public function modifyParticipation(int $userId, bool $confirm, bool $openSection): self
    {
        $i = $this->tester;
        if ($openSection) {
            $i->click('#participation-container > legend');
            $i->waitForElementVisible('#participants > li[data-participant="' . $userId . '"]');
        }
        $i->click('#participants > li[data-participant="' . $userId . '"]');
        $i->click('#customise-dropdown');
        $i->click('Exclude from assessment', '#customise-options');
        // If the user has submitted something, it'll display Please Confirm popup.
        if ($confirm) {
            $i->waitForText('Please Confirm');
            $i->click('Yes');
        }
        return $this;
    }
}
