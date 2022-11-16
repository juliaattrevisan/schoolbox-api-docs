<?php

namespace Page\Curriculum\Manage\action;

use AcceptanceTester;

class UpdateNode
{
    public const DISPLAY_TOGGLE = 'Update ';
    public const NAME_FIELD = '#name';
    public const CODE_FIELD = '#code';
    public const DESCRIPTION_FIELD = 'description-';
    public const UPDATE_BUTTON = '[data-test-update-node]';
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
        $this->tester->seeElement(self::UPDATE_BUTTON . ':disabled');
    }

    /**
     * update a name
     */
    public function fillName($value): void
    {
        $this->tester->fillField(self::NAME_FIELD, $value);
    }

    /**
     * update a code
     */
    public function fillCode($value): void
    {
        $this->tester->fillField(self::CODE_FIELD, $value);
    }

    /**
     * update a description
     */
    public function fillDescription($id, $value): void
    {
        $this->tester->fillCkEditorById(self::DESCRIPTION_FIELD . $id, $value);
    }

    /**
     * select the add button
     */
    public function clickUpdateButton(): void
    {
        $this->tester->click(self::UPDATE_BUTTON);
    }
}
