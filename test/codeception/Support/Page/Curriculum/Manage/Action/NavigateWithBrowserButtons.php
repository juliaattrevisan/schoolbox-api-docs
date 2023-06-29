<?php

namespace Tests\Support\Page\Curriculum\Manage\Action;

use Tests\Support\AcceptanceTester;

class NavigateWithBrowserButtons
{
    public const DISPLAY_TOGGLE = 'Expand ';
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
     * select a node to move to
     *
     * @param string $value
     */
    public function clickDisplay(string $value): void
    {
        $this->tester->click(self::DISPLAY_TOGGLE . $value);
    }

    /**
     * press the browser back button
     */
    public function moveBackwards(): void
    {
        $this->tester->moveBack();
    }

    /**
     * press the browser forward button
     */
    public function moveForwards(): void
    {
        $this->tester->moveForward();
    }
}
