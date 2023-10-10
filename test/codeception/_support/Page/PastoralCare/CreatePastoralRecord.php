<?php

namespace Page\PastoralCare;

use AcceptanceTester;

class CreatePastoralRecord
{
    public const URL = '/pastoral/student/{studentId}/record/insert';

    public const PASTORAL_TITLE = 'input#heading';

    public const PASTORAL_TYPE = 'select#typeId';
    public const PASTORAL_SUB_TYPE = 'select#subtypeId';
    public const SAVE_BUTTON = 'input.submit';
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
     * type a title for the pastoral record
     */
    public function typeTitle(string $pastoralTitle): void
    {
        $this->tester->fillField(self::PASTORAL_TITLE, $pastoralTitle);
    }


    /**
     * click type dropdown
     */
    public function clickTypeDropdown(): void
    {
        $this->tester->click(self::PASTORAL_TYPE);
    }


    /**
     * click subType dropdown
     */
    public function clickSubTypeDropdown(): void
    {
        $this->tester->click(self::PASTORAL_SUB_TYPE);
    }

    /**
     * click one of the types by passing its int value: 1 for Academic, 2 for Attendance, 3 for Medical, 4 for Bullying, 5 for Discipline
     * click one of the subtype by passing its int value: if type Academic then subtypes 1 for Award/Achievement, 2 for Learning Difficulties etc
     */
    public function selectTypeSubType(int $type): void
    {
        $this->tester->click('option[value="' . $type . '"]');
    }

    /**
     * click record visibility links by passing its title: Role, User, Pastoral Group
     */
    public function clickVisibilityLink(string $visibilityTitle): void
    {
        $this->tester->click('a[title="Add ' . $visibilityTitle . '"]');
    }


    /**
     * click a pastoral role in the dialog: Administration, Teacher, Parent
     */
    public function clickPastoralRole(string $role): void
    {
        $this->tester->click('li[data-role-name="' . $role . '"]');
    }

    /**
     * click Save button
     */
    public function clickSave(): void
    {
        $this->tester->click(self::SAVE_BUTTON);
    }
}
