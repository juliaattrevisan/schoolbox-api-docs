<?php

namespace Tests\Support\Page\Badges;

use Tests\Support\AcceptanceTester;
use Codeception\Util\Locator;
use Exception;

class Badges
{
    public const URL = '/learning/badge';

    public const BUTTON_ADD = 'a.icon-add.button';
    public const DROPDOWN_DELETE = 'a.icon-delete';
    public const BUTTON_CONFIRM_DELETE = 'a.button.submit';
    public const LIST_BOX_GOAL = "input[aria-controls='vs2__listbox']";
    public const LIST_BOX_AWARD   = "input[aria-controls='vs1__listbox']";
    public const USER_DROPDOWN    = "ul[role='listbox']";
    public const SELECTED_STUDENT = 'span.d-center';
    public const MODAL_BUTTON_SET_GOAL_ACHIEVEMENT = 'button';

    private AcceptanceTester $tester;

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
     * wait Create Badge button to be presented
     */
    public function waitAddBadgeButtonToBePresented(): void
    {
        $this->tester->waitForElementVisible(self::BUTTON_ADD, 2);
    }

    /**
     * click Create Badge button
     */
    public function clickAddBadgeButton(): void
    {
        $this->tester->click(self::BUTTON_ADD);
    }

    /**
     * verify badge name
     */
    public function verifyTextIsPresented($text): void
    {
        $this->tester->canSee($text);
    }

    /**
     * Click a badge dropdown
     */
    public function clickBadgeDropdown($badgeNumber): void
    {
        $this->tester->clickElementInWebComponent('[data-test=sbx-actions-dropdown-badge-' . $badgeNumber . '-dropdown]', '[data-test=sbx-actions-dropdown-badge-' . $badgeNumber . '-dropdown-button]');
    }

    /**
     * click an option in the dropdown (Award Achievement, Set Goal, etc)
     * options are: achievement, goal, withdraw-achievement, close, modify, delete
     */
    public function clickDropdownOptionLastElement($dropdownOption): void
    {
        $this->tester->click(Locator::lastElement('a.icon-' . $dropdownOption));
    }

    /**
     * click an option in the dropdown (Award Achievement, Set Goal, etc)
     * options are: achievement, goal, withdraw-achievement, close, modify, delete
     */
    public function clickDropdownOptionFirstElement($badgeId, $dropdownOption): void
    {
        $this->tester->click(Locator::firstElement("#badge-{$badgeId}-dropdown a.icon-" . $dropdownOption));
    }

    /**
     * click an option Modify in the dropdown
     */
    public function clickDropdownModify($badgeNumber): void
    {
        $this->tester->click('a[href="/learning/badge/' . $badgeNumber . '/update"]');
    }

    /**
     * click an option Delete in the dropdown
     */
    public function clickDropdownDelete(): void
    {
        $this->tester->click(Locator::lastElement(self::DROPDOWN_DELETE));
    }


    /**
     * click Delete button on confirmation dialog
     */
    public function clickConfirm(): void
    {
        $this->tester->click(Locator::lastElement(self::BUTTON_CONFIRM_DELETE));
    }

    /**
     * search and select a student on goal the modal
     */
    public function selectStudentSetGoal(string $studentName): void
    {
        $this->tester->fillField(self::LIST_BOX_GOAL, $studentName);
        $this->tester->waitForElement(self::USER_DROPDOWN);
        $this->tester->pressKey(self::LIST_BOX_GOAL, \Facebook\WebDriver\WebDriverKeys::ENTER);
        $this->tester->click(self::SELECTED_STUDENT);
    }

    /**
     * search and select a student on goal the modal
     */
    public function selectStudentAwardAchievement(string $studentName): void
    {
        $this->tester->fillField(self::LIST_BOX_AWARD, $studentName);
        $this->tester->waitForElement(self::USER_DROPDOWN);
        $this->tester->pressKey(self::LIST_BOX_AWARD, \Facebook\WebDriver\WebDriverKeys::ENTER);
        $this->tester->click(self::SELECTED_STUDENT);
    }

    /**
     * click Set Goal on the modal
     */
    public function clickSetGoalorAwardAchievement(): void
    {
        $this->tester->click(Locator::lastElement(self::MODAL_BUTTON_SET_GOAL_ACHIEVEMENT));
    }

    /**
     * click submit button on the evidence page
     */
    public function clickEvidenceSubmitButton(): void
    {
        $this->tester->click(("input[type=submit]"));
    }
}
