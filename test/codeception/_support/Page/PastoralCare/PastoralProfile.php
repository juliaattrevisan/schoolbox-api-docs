<?php

namespace Page\PastoralCare;

use AcceptanceTester;

class PastoralProfile
{
    public const URL = '/pastoral/student/{studentId}';

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
     * click Create Pastoral Record button
     */
    public function clickCreatePastoralRecord(): void
    {
        $this->tester->click('Create Pastoral Record');
    }

    /**
     * click Modify Pastoral Record icon
     */
    public function clickModifyIcon(int $studentId, int $recordId): void
    {
        $this->tester->click('href="/pastoral/student/' . $studentId . '/record/' . $recordId . '/update"');
    }

    /**
     * click Delete Pastoral Record icon
     */
    public function clickDeleteIcon(int $recordId): void
    {
        $this->tester->click('a[id="' . $recordId . '"');
    }


}
