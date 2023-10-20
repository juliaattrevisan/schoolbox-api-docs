<?php

declare(strict_types=1);

namespace Tests\Acceptance\Search;

use Tests\Support\AcceptanceTester;
use Tests\Support\Page\Search\SearchPage;
use Exception;

class SearchPageCest
{
    /**
     * Preparations
     *
     * @param AcceptanceTester $i
     * @throws Exception
     *
     * get logged in
     * navigate to https://vagrant.dev.schoolbox.cloud/search
     */
    public function _before(AcceptanceTester $i): void
    {
        $i->logout();
        $i->loginAsAdmin();
        $searchPage = new SearchPage($i);
        $i->amOnPage($searchPage::URL);
    }

    /**
     * @param AcceptanceTester $i
     * @return void
     * @throws Exception
     */
    public function searchEmptyKeyword(AcceptanceTester $i)
    {
        $searchPage = new SearchPage($i);

        $i->amGoingTo('click the search button without entering a keyword');
        $searchPage->clickSearchButton();

        $i->amGoingTo('expect to see the validation error for a minimum of 2 characters');
        $searchPage->verifySearchError();
    }

    public function searchValidKeyword(AcceptanceTester $i)
    {
        $searchPage = new SearchPage($i);

        $i->amGoingTo('input a valid keyword and click the search button');
        $searchPage->inputKeyword('vce');
        $searchPage->clickSearchButton();

        $i->amGoingTo('expect to see 5 results');
        $searchPage->verifyResultsCount(5);

        $i->amGoingTo('expect to see the "all results loaded" element');
        $searchPage->verifyAllResultsLoaded();
    }

    /**
     * @param AcceptanceTester $i
     * @return void
     * @throws Exception
     * @depends searchValidKeyword
     */
    public function searchNoFiltersAndCheckSubResults(AcceptanceTester $i)
    {
        $searchPage = new SearchPage($i);

        $i->amGoingTo('input a valid keyword and clear all filters');
        $searchPage->inputKeyword('vce');
        $searchPage->clearFilters();

        $i->amGoingTo('click the apply filters button and expect to see 10 results');
        $searchPage->clickApplyFilters();
        $searchPage->verifyResultsCount(10);

        $i->amGoingTo('load more results and expect to see the "all results loaded" element');
        $searchPage->clickLoadMoreResults();
        $searchPage->verifyAllResultsLoaded();

        $i->amGoingTo('load more sub results under Senior Campus and expect to see 10 sub results');
        $searchPage->clickLoadMoreSubResults(64);
        $searchPage->verifySubResultsCount(64, 10);
    }

    /**
     * @param AcceptanceTester $i
     * @return void
     * @throws Exception
     * @depends searchValidKeyword
     */
    public function searchFilterTypeNews(AcceptanceTester $i)
    {
        $searchPage = new SearchPage($i);

        $i->amGoingTo('input a valid keyword, clear filters, and click the news filter type');
        $searchPage->inputKeyword('vce');
        $searchPage->clearFilters();
        $searchPage->clickFilterType('news');

        $i->amGoingTo('click the apply filters button and expect to see 4 results');
        $searchPage->clickApplyFilters();
        $searchPage->verifyResultsCount(4);
    }

    /**
     * @param AcceptanceTester $i
     * @return void
     * @throws Exception
     * @depends searchValidKeyword
     */
    public function searchFilterCreator(AcceptanceTester $i)
    {
        $searchPage = new SearchPage($i);

        $i->amGoingTo('input a valid keyword and clear filters');
        $searchPage->inputKeyword('vce');
        $searchPage->clearFilters();

        $i->amGoingTo('input "white" into the creator search field');
        $searchPage->inputCreatorKeyword('white');

        $i->amGoingTo('select "Ms. Rebecca White - Teacher (501)" from the creator results');
        $searchPage->selectCreatorResult(24);

        $i->amGoingTo('Click the apply filters button and expect to see 1 result');
        $searchPage->clickApplyFilters();
        $searchPage->verifyResultsCount(1);
    }

    /**
     * @param AcceptanceTester $i
     * @return void
     * @throws Exception
     * @depends searchValidKeyword
     */
    public function searchFilterEndDate(AcceptanceTester $i)
    {
        $searchPage = new SearchPage($i);

        $i->amGoingTo('input a valid keyword and clear filters');
        $searchPage->inputKeyword('vce');
        $searchPage->clearFilters();

        $i->amGoingTo('input "14/01/2015" into the end date field');
        $searchPage->inputEndDate('14/01/2015');

        $i->amGoingTo('click the apply filters button and expect to see an empty results warning');
        $searchPage->clickApplyFilters();
        $searchPage->verifyNoResults();
    }

    /**
     * @param AcceptanceTester $i
     * @return void
     * @throws Exception
     */
    public function visitSearchPageWithPrefilledKeyword(AcceptanceTester $i)
    {
        $searchPage = new SearchPage($i);

        $i->amGoingTo('visit the search page with a pre-filled keyword');
        $i->amOnPage($searchPage::URL . '?keyword=musical');

        $i->amGoingTo('expect to see the pre-filled keyword');
        $searchPage->checkKeyword('musical');

        $i->amGoingTo('expect to see an empty results warning');
        $searchPage->verifyNoResults();
    }
}
