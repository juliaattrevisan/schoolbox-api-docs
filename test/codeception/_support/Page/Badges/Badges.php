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
     * click Create Badge button
     */
    public function clickAddBadgeButton(): void
    {
        $this->tester->click(self::BUTTON_ADD);
    }

    /**
     * verify badge name
     */
    public function verifyBadgeName($badgeName): void
    {
        $this->tester->canSee($badgeName);
    }
}
