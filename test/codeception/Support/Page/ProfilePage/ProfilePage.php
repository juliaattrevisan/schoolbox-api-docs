<?php

namespace Tests\Support\Page\ProfilePage;

use Tests\Support\AcceptanceTester;
use Codeception\Util\Locator;

class ProfilePage
{
    public const URL = '/search/user/{id}';

    /**
     * @var AcceptanceTester
     */
    private $tester;

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
     * @param int $id
     * @return string
     */
    public function generateRoute(int $id): string
    {
        return str_replace('{id}', (string)$id, self::URL);
    }

    /**
     * click one of the tabs in the profile page
     * options: activity, goals, achievements, classes, groups, contacts
     */
    public function clickTab(string $tabName): void
    {
        $this->tester->click('a[href="#tab-' . $tabName . '"]');
    }

    /**
     * click a Badge by passing its name
     * or
     * click a dropdown option: Remove goal, Award achievement, Withdraw achievement
     */
    public function clickBadgeOrDropdownOption(string $badgeNameOrDropdownOption): void
    {
        $this->tester->click(Locator::lastElement('a[title="' . $badgeNameOrDropdownOption . '"]'));
    }

    /**
     * Click a badge dropdown
     */
    public function clickBadgeGoalDropdown($badgeId): void
    {
        $this->tester->clickElementInWebComponent('[data-test=sbx-actions-dropdown-header-options-1-' . $badgeId . '-goal]', '[data-test=sbx-actions-dropdown-header-options-1-' . $badgeId . '-goal-button]');
    }

    /**
     *  Click a badge dropdown
     */
    public function clickBadgeAchievementDropdown($badgeId): void
    {
        $this->tester->clickElementInWebComponent('[data-test=sbx-actions-dropdown-header-options-1-' . $badgeId . '-achievement]', '[data-test=sbx-actions-dropdown-header-options-1-' . $badgeId . '-achievement-button]');
    }
}
