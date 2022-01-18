<?php

namespace Page\Badges;

use AcceptanceTester;
use Codeception\Util\Locator;
use Exception;

class Badges
{
    public const URL = '/learning/badge';

    public const BUTTON_ADD = 'a.icon-add.button';
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
     * click an option Odify in the dropdown
     */
    public function clickDropdownModify($badgeNumber): void
    {
        $this->tester->click('a[href="/learning/badge/' . $badgeNumber . '/update"]');
    }
}
