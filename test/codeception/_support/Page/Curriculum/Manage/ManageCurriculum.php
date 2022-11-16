<?php

namespace Page\Curriculum\Manage;

use AcceptanceTester;

class ManageCurriculum
{
    public const URL = '/curriculum';
    public const TOAST = '.toast';
    public const NODES = '[data-test-node]';
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
     * select the node checkbox to enable/disable or delete
     */
    public function checkNode($value): void
    {
        $this->tester->checkOption($value);
    }

    /**
     * select the node to navigate next o
     */
    public function clickNode($value): void
    {
        $this->tester->click($value);
    }

    /**
     * wait for the toast message to appear
     */
    public function waitForToast(): void
    {
        $this->tester->waitForElementVisible(self::TOAST, 2);
    }
}
