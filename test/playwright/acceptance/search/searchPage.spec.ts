/**
 * Check 2 characters warning, search terms and check the number of results presented, search without passing filters,
 * search by passing filters(news), search by creator, search by passing dates,
 * view search results by passing filters in the URL
 */
import { test } from "@schoolboxTest/playwright/support/integrations";
import SearchPage from "@schoolboxTest/playwright/support/page/search/SearchPage";

test.describe("Search functionality tests", () => {
    let searchPage: SearchPage;

    test.beforeEach(async ({ tester, page }) => {
        searchPage = new SearchPage(page);
        await tester.loginAsAdmin();
        await searchPage.goto();
    });

    test("Search with empty keyword should show error", async ({ tester }) => {
        tester.isGoingTo("click the search button without entering a keyword");
        await searchPage.clickSearchButton();
        tester.isGoingTo("expect to see the validation error for a minimum of 2 characters");
        await searchPage.verifySearchError();
    });

    test("Valid no-filter keyword search shows correct results count and all loaded message", async ({ tester }) => {
        tester.isGoingTo("input a valid keyword and click the search button");
        await searchPage.inputKeyword("Reed");
        await searchPage.clearFilters();
        await searchPage.clickSearchButton();

        tester.isGoingTo("expect to see 10 results");
        await searchPage.verifyResultsCount(10);

        tester.isGoingTo("expect to see the 'all results loaded' element");
        await searchPage.verifyAllResultsLoaded();
    });

    test("Valid no-filter keyword search with sub-results allows loading more", async ({ tester }) => {
        tester.isGoingTo("input a valid keyword and click the search button");
        await searchPage.inputKeyword("lesson");
        await searchPage.clearFilters();
        await searchPage.clickSearchButton();

        tester.isGoingTo("expect to initially see 7 sub results results for 9A French");
        await searchPage.verifySubResultsCount(52, 7);

        tester.isGoingTo("load more sub results under 9A French and expect to see 12 sub results");
        await searchPage.clickLoadMoreSubResults(52);
        await searchPage.verifySubResultsCount(52, 12);
    });

    test("Filter search by type news and verify results", async ({ tester }) => {
        tester.isGoingTo("input a valid keyword, clear filters, and click the news filter type");
        await searchPage.inputKeyword("vce");
        await searchPage.clearFilters();
        await searchPage.clickFilterType("news");

        tester.isGoingTo("click the apply filters button and expect to see 4 results");
        await searchPage.clickApplyFilters();
        await searchPage.verifyResultsCount(4);
    });

    test("Filter search by type creator and verify results", async ({ tester }) => {
        tester.isGoingTo("input a valid keyword and clear filters");
        await searchPage.inputKeyword("vce");
        await searchPage.clearFilters();

        tester.isGoingTo("input 'white' into the creator search field");
        await searchPage.inputCreatorKeyword("white");

        tester.isGoingTo("select 'Ms. Rebecca White - Teacher (501)' from the creator results");
        await searchPage.selectCreatorResult(24);

        tester.isGoingTo("Click the apply filters button and expect to see 1 result");
        await searchPage.clickApplyFilters();
        await searchPage.verifyResultsCount(1);
    });

    test("Filtering by end date with no results shows an empty results warning", async ({ tester }) => {
        tester.isGoingTo("input a valid keyword and clear filters");
        await searchPage.inputKeyword("vce");
        await searchPage.clearFilters();

        tester.isGoingTo("input '14/01/2015' into the end date field");
        await searchPage.inputEndDate("14/01/2015");

        tester.isGoingTo("click the apply filters button and expect to see an empty results warning");
        await searchPage.clickApplyFilters();
        await searchPage.verifyNoResults();
    });

    test("Visiting the search page with a prefilled keyword should search automatically", async ({ tester }) => {
        tester.isGoingTo("visit the search page with a pre-filled keyword");
        await searchPage.goto("?keyword=musical");

        tester.isGoingTo("expect to see the pre-filled keyword");
        await searchPage.verifyKeyword("musical");

        tester.isGoingTo("expect to see an empty results warning");
        await searchPage.verifyNoResults();
    });

    test.afterEach(async ({ tester }) => {
        await tester.logout();
    });
});
