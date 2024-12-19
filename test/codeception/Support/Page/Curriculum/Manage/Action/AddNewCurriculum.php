<?php

namespace Tests\Support\Page\Curriculum\Manage\Action;

use Tests\Support\AcceptanceTester;

class AddNewCurriculum
{
    public const DISPLAY_TOGGLE = '[data-test="toggle-create-curriculum"]';
    public const NAME_FIELD = '#name';
    public const CODE_FIELD = '#code';
    public const ADD_BUTTON = '[data-test="add-curriculum"]';
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
     * select the add new curriculum button
     */
    public function clickDisplay(): void
    {
        $this->tester->click(self::DISPLAY_TOGGLE);
    }

    /**
     * check if button is disabled, should be when no name has been entered
     */
    public function seeAddButtonIsDisabled(): void
    {
        $this->tester->seeElement(self::ADD_BUTTON . ':disabled');
    }

    /**
     * add a name
     *
     * @param string $value
     */
    public function fillName(string $value): void
    {
        $this->tester->fillField(self::NAME_FIELD, $value);
    }

    /**
     * add a code
     *
     * @param string $value
     */
    public function fillCode(string $value): void
    {
        $this->tester->fillField(self::CODE_FIELD, $value);
    }

    /**
     * select the add button
     */
    public function clickAddButton(): void
    {
        $this->tester->click(self::ADD_BUTTON);
    }
}
