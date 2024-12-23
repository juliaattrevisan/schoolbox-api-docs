<?php

namespace Tests\Support\Page\Curriculum\Manage\Action;

use Tests\Support\AcceptanceTester;

class DisableNodes
{
    public const DISABLE_BUTTON = '[data-test="disable-nodes"]';
    public const CONFIRM_BUTTON = '[data-test="confirm-enable-nodes"]';
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
     * click the button to disable the selected nodes
     */
    public function clickDisableButton(): void
    {
        $this->tester->click(self::DISABLE_BUTTON);
    }

    /**
     * wait for the confirmation modal to appear
     */
    public function waitForConfirmModal(): void
    {
        $this->tester->waitForElementVisible(self::CONFIRM_BUTTON, 2);
    }

    /**
     * click the confirm button to disable nodes
     */
    public function clickConfirmButton(): void
    {
        $this->tester->click(self::CONFIRM_BUTTON);
    }
}
