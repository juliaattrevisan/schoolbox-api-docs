<?php

namespace Tests\Support\Page\Curriculum\Manage\Action;

use Tests\Support\AcceptanceTester;

class DeleteNodes
{
    public const DELETE_BUTTON = '[data-test="delete-nodes"]';
    public const CONFIRM_BUTTON = '[data-test="confirm-delete-node"]';
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
     * click the button to delete the selected nodes
     */
    public function clickDeleteButton(): void
    {
        $this->tester->click(self::DELETE_BUTTON);
    }

    /**
     * wait for the confirmation modal to appear
     */
    public function waitForConfirmModal(): void
    {
        $this->tester->waitForElementVisible(self::CONFIRM_BUTTON, 2);
    }

    /**
     * click the confirm button to delete nodes
     */
    public function clickConfirmButton(): void
    {
        $this->tester->click(self::CONFIRM_BUTTON);
    }
}
