<?php

namespace Tests\Page\PastoralCare;

use Tests\Support\AcceptanceTester;

class PastoralProfile
{
    public const URL = '/pastoral/student/{studentId}';

    /**
     * @var AcceptanceTester
     */
    private $tester;

    public const DELETE_COMMENT_ICON = 'a[href="#delete-comment"]';


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
     * Generates route for this page.
     *
     * @param int $id
     * @return string
     */
    public function generateRoute(int $id): string
    {
        return str_replace('{studentId}', (string)$id, self::URL);
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
    public function clickDeleteIcon($recordId): void
    {
        $this->tester->click('a[id="' . $recordId . '"]');
    }

    /**
     * click a Pastoral Record
     */
    public function clickPastoralRecord($studentId, $recordId): void
    {
        $this->tester->click('a[href="/pastoral/student/' . $studentId . '/record/' . $recordId . '"]');
    }

    /**
     * click delete comment icon for learning moments, news, pastoral care records
     */
    public function clickDeleteCommentIcon(): void
    {
        $this->tester->click(self::DELETE_COMMENT_ICON);
    }
}
