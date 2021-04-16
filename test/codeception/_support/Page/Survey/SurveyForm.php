<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Page\Survey;

use AcceptanceTester;
use Alaress_Date_Format;
use Codeception\Util\Locator;

/**
 * Survey form page object
 */
class SurveyForm
{
    public const URL = '/survey/create/{class_id}';

    public const FORM = '#createAssess';

    // Not sure if every input should have its own selector constant
    public const SELECTOR_TITLE = self::FORM . ' input[name="title"]';
    public const SELECTOR_OPEN_DATE = self::FORM . ' input[name="openDate"]';
    public const SELECTOR_DUE_DATE = self::FORM . ' input[name="dueDate"]';
    public const SELECTOR_BUTTON_CREATE = self::FORM . ' input[type=submit][value="Create"]';
    public const SELECTOR_BUTTON_UPDATE = self::FORM . ' [type=submit][value="Update"]';

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
     * Navigates to create survey form
     * @param string $classId
     * @return SurveyForm
     */
    public function openCreate(string $classId): self
    {
        $i = $this->tester;
        $i->amOnPage(self::generateRoute($classId));
        return $this;
    }

    /**
     * Submits this form to create a survey
     * @return SurveyForm
     * @throws \Exception
     */
    public function create(): SectionForm
    {
        $i = $this->tester;
        $i->waitForElement(self::SELECTOR_BUTTON_CREATE);
        $i->scrollTo(['css' => self::SELECTOR_BUTTON_CREATE]);
        $i->waitForElementVisible(['css' => self::SELECTOR_BUTTON_CREATE],5);
        //$i->click(['css' => 'input[type=submit][value="Create"]']);
        $i->click(['css' => self::SELECTOR_BUTTON_CREATE]);
        $i->waitForElement('input[name="assess_id"]');
        return new SectionForm($i);
    }

    /**
     * Submits this form to update a quiz
     * @return SurveyForm
     */
    public function update(): self
    {
        $this->tester->click(self::SELECTOR_BUTTON_UPDATE);
        return $this;
    }

    /**
     * Sets title
     * @param string $title
     * @return SurveyForm
     */
    public function setTitle(string $title): self
    {
        $this->tester->fillField(self::SELECTOR_TITLE, $title);
        return $this;
    }

    /**
     * Sets open date
     * @param Alaress_Date_Format $openDate
     * @return SurveyForm
     */
    public function setOpenDate(Alaress_Date_Format $openDate): self
    {
        // Type in database format, let strtotime do the work
        // This is because Codeception has no access to the DB config
        $this->tester->fillDateField(self::SELECTOR_OPEN_DATE, $openDate->getDatabase());
        return $this;
    }
}
