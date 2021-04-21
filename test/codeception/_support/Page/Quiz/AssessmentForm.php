<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Page\Quiz;

use AcceptanceTester;
use Alaress_Date_Format;
use Codeception\Util\Locator;
use Facebook\WebDriver\WebDriverKeys;

/**
 * Quiz form page object
 */
class AssessmentForm
{
    public const URL = '/learning/folders/{class_id}/assessments/new?type=quiz';
    // http://192.168.36.10/learning/assessments/364/modify

    public const FORM = '#assessmentForm';

    // Not sure if every input should have its own selector constant
    public const SELECTOR_TITLE = self::FORM . ' input[name="title"]';
    public const SELECTOR_WORKTYPE = self::FORM . ' select[name="workType"]';
    public const SELECTOR_VISIBILITY = self::FORM . ' select[name="visibility"]';
    public const SELECTOR_MAX_ATTEMPTS = self::FORM . ' select[name="maxAttempts"]';
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
    public const VISIBILITY_STAFF_ONLY = 0;
    public const VISIBILITY_STAFF_AND_STUDENT = 1;
    public const VISIBILITY_ALL = 2;

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
     * Submits this form to create a quiz
     * @return AssessmentForm
     * @throws \Exception
     */
    public function create(): self
    {
        $i = $this->tester;
        $i->scrollTo(['css' => self::SELECTOR_BUTTON_CREATE]);
        $i->waitForElement(self::SELECTOR_BUTTON_CREATE);
        $i->click(self::SELECTOR_BUTTON_CREATE);
        $i->waitForElement('input[name="assess_id"]');
        return $this;
    }

    /**
     * Submits this form to update a quiz
     * @return AssessmentForm
     * @throws \Exception
     */
    public function update(): self
    {
        $i = $this->tester;
       // $element = Locator::contains(self::SELECTOR_BUTTON_UPDATE, 'Update');
        //$element = (Locator::lastElement(self::SELECTOR_BUTTON_UPDATE));
       // $i->scrollTo('input#curriculum-code-select-selectized');
       // $i->executeJS('window.scrollTo(0,35000);');
       // $i->scrollTo(Locator::contains(self::SELECTOR_BUTTON_UPDATE, 'Update'),500,20);
       // $element = Locator::contains('a.icon.modify','My Links');
        $i->pressKey(self::SELECTOR_BUTTON_UPDATE,WebDriverKeys::PAGE_DOWN);
        $i->waitForElementVisible(self::SELECTOR_BUTTON_UPDATE,5);
        $i->click(self::SELECTOR_BUTTON_UPDATE);
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
     * Sets max attempts
     * @param int $maxAttempts
     * @return AssessmentForm
     */
    public function setMaxAttempts(int $maxAttempts): self
    {
        $this->tester->selectOption(self::SELECTOR_MAX_ATTEMPTS, $maxAttempts);
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
}
