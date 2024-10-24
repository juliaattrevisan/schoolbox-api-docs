<?php

namespace Tests\Support\Page\Search;

use Tests\Support\AcceptanceTester;
use Codeception\Util\Locator;
use Exception;

class SearchPage
{
    public const URL = '/search';

    // data-test selector values for elements on the search page
    public const RESULTS_RESULT = 'sp-results-result'; // ID 1 = Result ID
    public const RESULTS_ERROR = 'sp-results-error';
    public const RESULTS_NONE = 'sp-results-none';
    public const RESULTS_LOAD_MORE_BUTTON = 'sp-results-load-more-button';
    public const RESULTS_LOAD_MORE_ALL_LOADED = 'sp-results-load-more-all-loaded';
    public const RESULTS_SUB_RESULTS_RESULT = 'sp-results-sub-results-result'; // ID 1 = Result ID, ID 2 = Sub-result ID
    public const RESULTS_SUB_RESULTS_LOAD_MORE = 'sp-results-sub-results-load-more'; // ID 1 = Result ID
    public const SEARCH_FIELD = 'sp-search-field';
    public const SUBMIT_BUTTON = 'sp-submit-button';
    public const FILTER_CLEAR_BUTTON = 'sp-filter-clear-button';
    public const FILTER_APPLY_BUTTON = 'sp-filter-apply-button';
    public const FILTER_DATE_RANGE_END = 'sp-filter-date-range-end';
    public const FILTER_TYPE_LABEL = 'sp-filter-type-label'; // ID 1 = Type, such as "news"
    public const CREATOR_SEARCH_SELECT_INPUT = 'ss-query-input';
    public const CREATOR_SEARCH_SELECT_RESULT = 'search-select-result'; // ID 1 = User ID

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
     * Click the search submit button
     */
    public function clickSearchButton(): void
    {
        $this->tester->click($this->tester->getTestSelector(self::SUBMIT_BUTTON));
    }

    /**
     * Verify the contents of the search error message
     */
    public function verifySearchError(): void
    {
        $this->tester->canSee(
            "Search keyword must be at least 2 characters",
            $this->tester->getTestSelector(self::RESULTS_ERROR)
        );
    }

    /**
     * Verify the number of results displayed
     */
    public function verifyResultsCount(int $count): void
    {
        $this->tester->seeNumberOfElements(
            $this->tester->getTestSelector(self::RESULTS_RESULT, true),
            $count
        );
    }

    /**
     * Verify all results are loaded
     */
    public function verifyAllResultsLoaded(): void
    {
        $this->tester->seeElement($this->tester->getTestSelector(self::RESULTS_LOAD_MORE_ALL_LOADED));
    }

    /**
     * Verify no results are displayed
     */
    public function verifyNoResults(): void
    {
        $this->tester->seeElement($this->tester->getTestSelector(self::RESULTS_NONE));
    }

    /**
     * Input a keyword in the search field
     *
     * @param string $keyword
     */
    public function inputKeyword(string $keyword): void
    {
        $this->tester->fillField(
            $this->tester->getTestSelector(self::SEARCH_FIELD),
            $keyword
        );
    }

    /**
     * Check what is inside the search field
     *
     * @param string $keyword
     */
    public function checkKeyword(string $keyword): void
    {
        $this->tester->seeInField(
            $this->tester->getTestSelector(self::SEARCH_FIELD),
            $keyword
        );
    }

    /**
     * Clear all filters
     */
    public function clearFilters(): void
    {
        $this->tester->click($this->tester->getTestSelector(self::FILTER_CLEAR_BUTTON));
    }

    /**
     * Click the apply filters button
     */
    public function clickApplyFilters(): void
    {
        $this->tester->click($this->tester->getTestSelector(self::FILTER_APPLY_BUTTON));
    }

    /**
     * Click the load more results button
     */
    public function clickLoadMoreResults(): void
    {
        $this->tester->click($this->tester->getTestSelector(self::RESULTS_LOAD_MORE_BUTTON));
    }

    /**
     * Click the load more sub results button
     *
     * @param int $resultId
     */
    public function clickLoadMoreSubResults(int $resultId): void
    {
        $this->tester->click(
            $this->tester->getTestSelector(self::RESULTS_SUB_RESULTS_LOAD_MORE, false, [$resultId])
        );
    }

    /**
     * Verify the number of sub results displayed
     *
     * @param int $resultId
     * @param int $count
     */
    public function verifySubResultsCount(int $resultId, int $count): void
    {
        $this->tester->seeNumberOfElements(
            $this->tester->getTestSelector(self::RESULTS_SUB_RESULTS_RESULT, true, [$resultId]),
            $count
        );
    }

    /**
     * Input a date in the end date field
     *
     * @param string $date
     */
    public function inputEndDate(string $date): void
    {
        $this->tester->fillField(
            $this->tester->getTestSelector(self::FILTER_DATE_RANGE_END),
            $date
        );
    }

    /**
     * Click a filter type (e.g., news)
     *
     * @param string $type
     */
    public function clickFilterType(string $type): void
    {
        $this->tester->click(
            $this->tester->getTestSelector(self::FILTER_TYPE_LABEL . '-' . $type)
        );
    }

    /**
     * Input a keyword in the creator search field
     *
     * @param string $keyword
     */
    public function inputCreatorKeyword(string $keyword): void
    {
        $this->tester->fillField(
            $this->tester->getTestSelector(self::CREATOR_SEARCH_SELECT_INPUT),
            $keyword
        );
    }

    /**
     * Select a creator result by ID
     *
     * @param int $id
     */
    public function selectCreatorResult(int $id): void
    {
        $this->tester->click(
            $this->tester->getTestSelector(self::CREATOR_SEARCH_SELECT_RESULT . '-' . $id)
        );
    }
}
