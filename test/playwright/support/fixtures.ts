/**
 * Fixtures for Playwright tests.
 * https://playwright.dev/docs/test-fixtures
 */
import { test as base } from "@playwright/test";
import { BaseTester } from "@schoolboxTest/playwright/support/baseTester";
import { AcceptanceTester } from "@schoolboxTest/playwright/support/acceptanceTester";
import { DatabaseHelper } from "@schoolboxTest/playwright/support/databaseHelper";

type Fixtures = {
    db: DatabaseHelper;
    tester: AcceptanceTester;
    baseTester: BaseTester;
};

export const test = base.extend<Fixtures>({
    // eslint-disable-next-line no-empty-pattern -- Playwright requires first argument to follow the pattern
    db: async ({}, use) => {
        const db = new DatabaseHelper();
        try {
            await use(db);
        } finally {
            await db.closePool();
        }
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
