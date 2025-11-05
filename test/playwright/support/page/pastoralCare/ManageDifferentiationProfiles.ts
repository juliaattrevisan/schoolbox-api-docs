/**
 * Utility class for manage differentiation profiles page
 */

import type { Page } from "@playwright/test";
import type AcceptanceTester from "@schoolboxTest/playwright/support/AcceptanceTester";
import AdminDifferentiationProfiles from "@schoolboxTest/playwright/support/page/admin/differentiation-profiles/AdminDifferentiationProfiles";
import ManageRecordSettings from "@schoolboxTest/playwright/support/page/pastoralCare/ManageRecordSettings";
import type DatabaseHelper from "../../DatabaseHelper";

export default class ManageDifferentiationProfiles {
    private tester: AcceptanceTester;

    private page: Page;

    static readonly DATA_TEST_TOGGLE = "differentiation-profiles-toggle";

    static readonly DATA_TEST_TERMINOLOGY_HEADER = "differentiation-profiles-header-Terminology";

    constructor(tester: AcceptanceTester, page: Page) {
        this.tester = tester;
        this.page = page;
    }

    /**
     * Navigates to the differentiation profiles admin page
     */
    async navigateToPage(): Promise<void> {
        this.tester.isGoingTo("Visit the manage differentiation profiles page");
        await this.page.goto("/differentiation-profiles/manage");
    }

    /**
     * Enable the page enabling differentiation profiles and granting service permission
     */
    async enablePage(db: DatabaseHelper): Promise<void> {
        const adminDifferentiationProfiles = new AdminDifferentiationProfiles(this.tester, this.page);
        await adminDifferentiationProfiles.navigateToPage();
        await adminDifferentiationProfiles.toggleOn();
        this.tester.isGoingTo("Grant service permission to admin user");
        await db.haveInDatabase("userAccess", { uid: 1, service: 16, sid: 1, type: 2 });
        await db.haveInDatabase("userAccess", { uid: 1, service: 16, sid: 2, type: 2 });
        await this.tester.loginAsAdmin(); // Re-login to refresh permissions
    }

    /**
     * Enable the DP toggle on a pastoral care type bullying and award achievement
     */
    async addPastoralTypes(): Promise<void> {
        this.tester.isGoingTo("Enable the DP toggle on a pastoral care type bullying and award achievement");
        await this.tester.impersonate("Jack Coles");
        const manageRecordSettings = new ManageRecordSettings(this.page, this.tester);
        await manageRecordSettings.gotoBullying();
        await manageRecordSettings.clickApplyToDifferentiationProfiles();
        await manageRecordSettings.clickSave();
        await manageRecordSettings.gotoAwardAchievement();
        await manageRecordSettings.clickApplyToDifferentiationProfiles();
        await manageRecordSettings.clickSave();
    }
}
