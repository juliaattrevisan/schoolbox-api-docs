import { test } from "@schoolboxTest/playwright/support/fixtures";
import { SearchPage } from "@schoolboxTest/playwright/support/page/search/searchPage";

test.describe("Search functionality tests", () => {
    let searchPage: SearchPage;

    test.beforeEach(async ({ tester, page }) => {
        searchPage = new SearchPage(page);
        await tester.loginAsAdmin();
        await searchPage.goto();
    });

    test("Search with empty keyword should show error", async ({ tester }) => {
        await searchPage.clickSearchButton();
        await searchPage.verifySearchError();
    });

    test("Valid no-filter keyword search shows correct results count and all loaded message", async ({ tester }) => {
        await searchPage.inputKeyword("vce");
        await searchPage.clickSearchButton();

        await searchPage.verifyResultsCount(5);
        await searchPage.verifyAllResultsLoaded();
    });

    test("Valid no-filter keyword search with sub-results allows loading more", async ({ tester }) => {
        await searchPage.inputKeyword("help");
        await searchPage.clearFilters();
        await searchPage.clickSearchButton();

        await searchPage.verifyResultsCount(15);

        await searchPage.inputKeyword("help");
        await searchPage.clearFilters();
        await searchPage.clickSearchButton();
        await searchPage.clickLoadMoreSubResults(168);
        await searchPage.verifySubResultsCount(168, 6);
    });

    test("Filter search by type news and verify results", async ({ tester }) => {
        await searchPage.inputKeyword("vce");
        await searchPage.clearFilters();
        await searchPage.clickFilterType("news");

        await searchPage.clickApplyFilters();
        await searchPage.verifyResultsCount(4);
    });

    test("Filter search by type creator and verify results", async ({ tester }) => {
        await searchPage.inputKeyword("vce");
        await searchPage.clearFilters();

        await searchPage.inputCreatorKeyword("white");

        await searchPage.selectCreatorResult(24);

        await searchPage.clickApplyFilters();
        await searchPage.verifyResultsCount(1);
    });

    test("Filtering by end date with no results shows an empty results warning", async ({ tester }) => {
        await searchPage.inputKeyword("vce");
        await searchPage.clearFilters();

        await searchPage.inputEndDate("14/01/2015");

        await searchPage.clickApplyFilters();
        await searchPage.verifyNoResults();
    });

    test("Visiting the search page with a prefilled keyword should search automatically", async ({ tester }) => {
        await searchPage.goto("?keyword=musical");

        await searchPage.verifyKeyword("musical");
        await searchPage.verifyNoResults();
    });

    test.afterEach(async ({ tester }) => {
        await tester.logout();
    });
});
