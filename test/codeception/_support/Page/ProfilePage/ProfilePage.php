<?php

namespace Page\ProfilePage;

use AcceptanceTester;

class ProfilePage
{
    public const URL = '/search/user/{id}';

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
     * Generates route for this page.
     *
     * @param int $id
     * @return string
     */
    public function generateRoute(int $id): string
    {
        return str_replace('{id}', (string)$id, self::URL);
    }

    /**
     * click one of the tabs in the profile page
     * options: activity, goals, achievements, classes, groups, contacts
     */
    public function clickTab(string $tabName): void
    {
        $this->tester->click('a[href="#tab-' . $tabName . '"]');
    }
}
