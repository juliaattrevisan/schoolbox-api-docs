<?php

namespace Page\Curriculum\Manage\action;

use AcceptanceTester;

class AddNewSubsection
{

    public const DISPLAY_TOGGLE = 'Add subsection to ';
    public const NAME_FIELD = '#name';
    public const CODE_FIELD = '#code';
    public const DESCRIPTION_FIELD = '#description-';
    public const ADD_BUTTON = '[data-test-add-subsection]';
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
     * select the add new subsection button
     */
    public function clickDisplay($value): void
    {
        $this->tester->click(self::DISPLAY_TOGGLE . $value);
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
     */
    public function fillName($value): void
    {
        $this->tester->fillField(self::NAME_FIELD, $value);
    }

    /**
     * add a code
     */
    public function fillCode($value): void
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
