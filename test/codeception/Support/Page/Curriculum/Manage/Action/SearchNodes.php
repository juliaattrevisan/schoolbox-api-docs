<?php

namespace Tests\Support\Page\Curriculum\Manage\Action;

use Tests\Support\AcceptanceTester;
use Tests\Support\Page\Curriculum\Manage\ManageCurriculum;

class SearchNodes
{
    public const SEARCH_FIELD = '[data-test="search-input"]';
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
     *
     * @param string $value
     */
    public function fillSearch(string $value): void
    {
        $this->tester->fillField(self::SEARCH_FIELD, $value);
    }

    /**
     * to check the number of expected results matches the actual
     *
     * @param int $value
     */
    public function seeResultsMatch(int $value): void
    {
        $this->tester->seeNumberOfElements(ManageCurriculum::NODES, $value);
    }

    /**
     * hide disabled nodes
     */
    public function uncheckShowDisabledNodes(): void
    {
        $this->tester->uncheckOption(self::TOGGLE_SHOW_DISABLED_NODES);
    }
}
