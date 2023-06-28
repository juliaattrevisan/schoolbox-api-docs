<?php

namespace Tests\Support\Page\Curriculum\Manage;

use Tests\Support\AcceptanceTester;

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
     *
     * @param string $value
     */
    public function checkNode(string $value): void
    {
        $this->tester->checkOption($value);
    }

    /**
     * select the node to navigate next to
     *
     * @param string $value
     */
    public function clickNode(string $value): void
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
