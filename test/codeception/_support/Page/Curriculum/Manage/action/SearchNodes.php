<?php

namespace Page\Curriculum\Manage\action;

use AcceptanceTester;
use Page\Curriculum\Manage\ManageCurriculum;

class SearchNodes
{

    public const SEARCH_FIELD = '[data-test-search-input]';
    public const TOGGLE_SHOW_DISABLED_NODES = '[data-test-show-only-enabled]';
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
     * add a search
     */
    public function fillSearch($value): void
    {
        $this->tester->fillField(self::SEARCH_FIELD, $value);
    }

    /**
     * to check the number of expected results matches the actual
     */
    public function seeResultsMatch($value): void
    {
        $this->tester->seeNumberOfElements(ManageCurriculum::NODES, $value);
    }

}
