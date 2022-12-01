<?php

namespace Page\Badges;

use AcceptanceTester;
use Codeception\Util\Locator;
use Exception;

class Badges
{
    public const URL = '/learning/badge';

    public const BUTTON_ADD = 'a.icon-add.button';
    public const DROPDOWN_SET_GOAL = 'a.icon-goal';
    public const DROPDOWN_DELETE = 'a.icon-delete';
    public const BUTTON_CONFIRM_DELETE = 'a.button.submit';
    public const LIST_BOX = "input[aria-controls='vs2__listbox']";
    public const SELECTED_STUDENT = 'span.selected.d-center';
    public const MODAL_BUTTON_SET_GOAL = 'button';

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
        $this->tester->click('a[data-dropdown="badge-' . $badgeNumber . '-dropdown"]');
    }

    /**
     * click an option in the dropdown (Award Achievement, Set Goal, etc)
     */
    public function clickDropdownOption($dropdownOption): void
    {
        $this->tester->click('a.icon-' . $dropdownOption);
    }

    /**
     * click an option Modify in the dropdown
     */
    public function clickDropdownModify($badgeNumber): void
    {
        $this->tester->click('a[href="/learning/badge/' . $badgeNumber . '/update"]');
    }

    /**
     * click an option Set Goal in the dropdown
     */
    public function clickDropdownSetGoal(): void
    {
        $this->tester->click(Locator::lastElement(self::DROPDOWN_SET_GOAL));
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
    public function clickConfirmDelete(): void
    {
        $this->tester->click(Locator::lastElement(self::BUTTON_CONFIRM_DELETE));
    }

    /**
     * set goal/achievement modal
     */
    public function selectStudent(string $studentName): void
    {
        $this->tester->fillField(self::LIST_BOX, $studentName);
        $this->tester->pressKey(self::LIST_BOX, \Facebook\WebDriver\WebDriverKeys::ENTER);
        $this->tester->click(self::SELECTED_STUDENT);
    }

    /**
     * click Set Goal
     */
    public function clickSetGoal(): void
    {
        $this->tester->click(Locator::lastElement(self::MODAL_BUTTON_SET_GOAL));
    }
}
