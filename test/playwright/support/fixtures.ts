/**
 * Fixtures for Playwright tests.
 * https://playwright.dev/docs/test-fixtures
 */
import { test as base } from "@playwright/test";
import { DatabaseHelper } from "@schoolboxTest/playwright/support/databaseHelper";

type Fixtures = {
    db: DatabaseHelper;
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
});

export { expect } from "@playwright/test";
