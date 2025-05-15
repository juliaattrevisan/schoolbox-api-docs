/**
 * Integrations for Playwright tests.
 * These provide additional functionality and helper methods for use in tests.
 * Note: Playwright calls these "fixtures", but we have called them "integrations" instead
 *  to avoid confusion with our own Fixture system.
 * https://playwright.dev/docs/test-fixtures
 */
import { test as base } from "@playwright/test";
import BaseTester from "@schoolboxTest/playwright/support/BaseTester";
import AcceptanceTester from "@schoolboxTest/playwright/support/AcceptanceTester";
import DatabaseHelper from "@schoolboxTest/playwright/support/DatabaseHelper";
import ApiHelper from "@schoolboxTest/playwright/support/ApiHelper";

type Integrations = {
    db: DatabaseHelper;
    api: ApiHelper;
    tester: AcceptanceTester;
    baseTester: BaseTester;
};

export const test = base.extend<Integrations>({
    // eslint-disable-next-line no-empty-pattern -- Playwright requires first argument to follow the pattern
    db: async ({}, use) => {
        const db = new DatabaseHelper();
        try {
            await use(db);
        } finally {
            await db.closePool();
        }
    },
    api: async ({ request, baseTester }, use) => {
        const api = new ApiHelper(request, baseTester);
        await use(api);
    },
    tester: ({ page }, use) => {
        const tester = new AcceptanceTester(page);
        return use(tester);
    },
    // eslint-disable-next-line no-empty-pattern -- Playwright requires first argument to follow the pattern
    baseTester: async ({}, use) => {
        const baseTester = new BaseTester();
        return use(baseTester);
    },
});

export { expect } from "@playwright/test";
