import { expect } from "@playwright/test";
import type { Page } from "@playwright/test";
import Common from "@schoolboxTest/playwright/support/page/Common";
import type AcceptanceTester from "@schoolboxTest/playwright/support/AcceptanceTester";
import type DatabaseHelper from "@schoolboxTest/playwright/support/DatabaseHelper";

export default class Fixture {
    private tester: AcceptanceTester;

    private page: Page;

    static readonly URL_CREATE_MATCH = "/fixtures/divisions/{division_id}/matches/new?season={season_id}";

    static readonly URL_UPDATE_MATCH = "/fixtures/matches/{match_id}/edit";

    static readonly SELECTOR_NAME = 'input[name="name"]';

    static readonly SELECTOR_START_AT = "#startAtDate";

    static readonly SELECTOR_FINISH_AT = "#finishAtDate";

    static readonly SELECTOR_JS_VENUE = "#venue-selectized";

    static readonly SELECTOR_JS_HOME_TEAM = "#homeTeam-selectized";

    static readonly SELECTOR_JS_AWAY_TEAM = "#awayTeam-selectized";

    static readonly SELECTOR_DELETE_ITINERARY = "[data-item-list] [data-delete]:visible";

    static readonly VENUE_OAKLEIGH_RECREATION = 2;

    static readonly TEAM_WARRIORS = 1;

    static readonly TEAM_RAIDERS = 2;

    constructor(tester: AcceptanceTester, page: Page) {
        this.tester = tester;
        this.page = page;
    }

    /**
     * Get URL to create a match
     * @param divisionId Division ID
     * @param seasonId Season ID
     * @returns URL
     */
    static getCreateMatchUrl(divisionId: number, seasonId: number): string {
        return Fixture.URL_CREATE_MATCH.replace("{division_id}", `${divisionId}`).replace("{season_id}", `${seasonId}`);
    }

    /**
     * Get URL to update a match
     * @param matchId Match ID
     * @returns URL
     */
    static getUpdateMatchUrl(matchId: number): string {
        return Fixture.URL_UPDATE_MATCH.replace("{match_id}", `${matchId}`);
    }

    /**
     * Create a match step by step
     * @param db Database helper
     * @param fields fields
     * @param jsFields JS fields
     * @returns Match ID
     */
    async createMatch(
        db: DatabaseHelper,
        fields: Record<string, string | Date>,
        jsFields: Record<string, number>,
    ): Promise<number> {
        await this.page.goto(Fixture.getCreateMatchUrl(1, 4));
        await expect(this.page.locator("h2.subheader").filter({ hasText: "CREATE MATCH" })).toBeVisible();

        let date = "";
        let foundDate = false;
        let startAtDb = "";

        const parsedFields: Record<string, string> = {};

        this.tester.isGoingTo("fill in each field");
        await Object.entries(fields).reduce(async (promise, [fieldName, fieldValue]) => {
            await promise; // wait for the previous field to be filled

            // if the field is a date then add the date
            if (fieldValue instanceof Date) {
                fieldValue.setSeconds(0); // so the date matches the database
                const fieldDate = Common.formatDate(fieldValue, true);
                // if a field date is found for the first time then record its date
                if (!foundDate) {
                    date = fieldDate;
                    startAtDb = Common.formatDateDB(fieldValue, true);
                }
                parsedFields[fieldName] = fieldDate;
                // the field date was field once so it won't record dates for the other fields (eg: finishDate)
                foundDate = true;
            } else {
                parsedFields[fieldName] = fieldValue;
            }

            await this.page.locator(fieldName).fill(parsedFields[fieldName]);
        }, Promise.resolve());

        // Add itinerary, use keyboard to type since jquery.mightyList.js doesn't work well with fill
        this.tester.isGoingTo("Add itinerary");
        await this.page.locator("[data-item-add]").click();
        // <input type="text" name="itinerary[date][]" id="itineraryDate-new-1529450182910" placeholder="Date" data-itinerary-date="" data-datepicker-time="" value="" data-datepicker-id="2">
        await this.page.locator('li:not([data-template]) input[name="itinerary[date][]"]').click();
        await this.page.keyboard.type(date);
        await this.page.locator('li:not([data-template]) input[name="itinerary[description][]"]').click();
        await this.page.keyboard.type("lalala");
        await this.page.keyboard.press("Escape");

        // Wait for another row to be added to the itinerary
        await expect(this.page.locator("[data-item-list] li[data-item]")).toHaveCount(2);

        // Interact with the JS fields (selectize)
        this.tester.isGoingTo("fill in each selectize field");
        await Object.entries(jsFields).reduce(async (promise, [fieldName, fieldValue]) => {
            await promise;

            // Click escape first in case another dropdown is open
            await this.page.keyboard.press("Escape");
            // Then click on the input
            await this.page.locator(fieldName).click();
            // Then find the open dropdown and click on the right option
            await this.page.locator(`.selectize-dropdown-content:visible [data-value="${fieldValue}"]`).click();
            // Then click escape again to close the dropdown
            await this.page.keyboard.press("Escape");
        }, Promise.resolve());

        // Click the submit button
        this.tester.isGoingTo("submit the form");
        await this.page.click('#matchForm [type=submit][value="Create Match"]');
        await expect(this.page.locator("h1").filter({ hasText: "Seasons" })).toBeVisible();

        this.tester.isGoingTo(
            `grab the match ID from the database with name ${parsedFields[Fixture.SELECTOR_NAME]} and start date ${startAtDb}`,
        );
        const matchIds = await db.grabColumnFromDatabase("sportsfixtures_match", "id", {
            name: parsedFields[Fixture.SELECTOR_NAME],
            start_at: startAtDb,
        });

        if (matchIds.length === 0 || typeof matchIds[0] !== "number") {
            throw new Error(`Expected match ID, found "${matchIds.toString()}"`);
        }

        return matchIds[0];
    }

    /**
     * Update a match step by step
     * @param matchId Match ID
     * @param changes Changes to apply
     */
    async updateMatch(matchId: number, changes: Record<string, string | Date>): Promise<void> {
        await this.page.goto(Fixture.getUpdateMatchUrl(matchId));
        await expect(this.page.locator("h2.subheader").filter({ hasText: "MODIFY MATCH" })).toBeVisible();

        // for each field and its value
        await Object.entries(changes).reduce(async (promise, [fieldName, fieldValue]) => {
            await promise; // wait for the previous field to be filled

            // if the field is a date then convert the date
            const parsedValue = fieldValue instanceof Date ? Common.formatDate(fieldValue, true) : fieldValue;
            await this.page.locator(fieldName).fill(parsedValue);
        }, Promise.resolve());

        await this.page.click('#matchForm [type=submit][value="Modify Match"]');
        await expect(this.page.locator("h1").filter({ hasText: "Seasons" })).toBeVisible();
    }

    /**
     * Add itinerary to a match
     * @param matchId Match ID
     */
    async addItinerary(matchId: number): Promise<void> {
        await this.page.goto(Fixture.getUpdateMatchUrl(matchId));
        await expect(this.page.locator("h2.subheader").filter({ hasText: "MODIFY MATCH" })).toBeVisible();

        const now = new Date();
        const startAt = Common.formatDate(now, true);

        const initialRowCount = await this.page.locator("[data-item-list] li[data-item]:visible").count();

        // Add itinerary if there are no rows (click the empty state's add button)
        if (initialRowCount === 0) {
            await this.page.locator("[data-item-add]").click();
        }

        const lastDateRow = this.page.locator('[data-item-list] li input[name="itinerary[date][]"]').last();
        const lastDescriptionRow = this.page
            .locator('[data-item-list] li input[name="itinerary[description][]"]')
            .last();

        // Rows should be empty
        await expect(lastDateRow).toHaveText("");
        await expect(lastDescriptionRow).toHaveText("");

        // Fill the date and description
        await lastDateRow.click();
        await this.page.keyboard.type(startAt);
        await lastDescriptionRow.click();
        await this.page.keyboard.type("lalala");
        await this.page.keyboard.press("Escape");

        // Wait for another row to be added to the itinerary
        await expect(this.page.locator("[data-item-list] li[data-item]")).toHaveCount(initialRowCount + 1);

        await this.page.click('#matchForm [type=submit][value="Modify Match"]');
        await expect(this.page.locator("h1").filter({ hasText: "Seasons" })).toBeVisible();
    }

    /**
     * Delete itinerary from a match
     * @param matchId Match ID
     */
    async deleteItinerary(matchId: number): Promise<void> {
        await this.page.goto(Fixture.getUpdateMatchUrl(matchId));
        await expect(this.page.locator("h2.subheader").filter({ hasText: "MODIFY MATCH" })).toBeVisible();

        // Click all delete buttons so the empty state is shown
        const deleteButtons = await this.page.locator(Fixture.SELECTOR_DELETE_ITINERARY).all();
        await deleteButtons.reduce(async (promise) => {
            await promise;
            // Find the first one, since it might change after each click
            await this.page.locator(Fixture.SELECTOR_DELETE_ITINERARY).first().click();
        }, Promise.resolve());

        await this.page.locator('#matchForm [type=submit][value="Modify Match"]').click();
        await expect(this.page.locator("h1").filter({ hasText: "Seasons" })).toBeVisible();
    }

    /**
     * Assert that a notification exists for Jana Reed and not the current user
     * - Expects the notification not to exist for the current user
     * - Logs in as Jana Reed and expects the notification to exist
     * - Logs back in as the current user
     * @param text the text to verify
     */
    async assertNotifExists(text: string): Promise<void> {
        this.tester.isGoingTo("expect the notification to not exist for the current user (admin)");
        await this.tester.common.assertNotifNotExists(text);

        this.tester.isGoingTo("login as Jana Reed and expect the notification to exist");
        await this.tester.impersonateViaModifyUserPage("Ms Jana Reed");
        await this.tester.common.assertNotifExists(text);

        this.tester.isGoingTo("login back as the current user (admin)");
        await this.tester.logout();
        await this.tester.loginAsAdmin();
    }

    /**
     * Assert that a notification does not exist for the current user or Jana Reed
     * - Expects the notification not to exist for the current user
     * - Logs in as Jana Reed and expects the notification not to exist
     * - Logs back in as the current user
     * @param text the text to verify
     */
    async assertNotifNotExists(text: string): Promise<void> {
        this.tester.isGoingTo("expect the notification to not exist for the current user (admin)");
        await this.tester.common.assertNotifNotExists(text);

        this.tester.isGoingTo("login as Jana Reed and expect the notification to not exist");
        await this.tester.impersonateViaModifyUserPage("Ms Jana Reed");
        await this.tester.common.assertNotifNotExists(text);

        this.tester.isGoingTo("login back as the current user (admin)");
        await this.tester.logout();
        await this.tester.loginAsAdmin();
    }
}
