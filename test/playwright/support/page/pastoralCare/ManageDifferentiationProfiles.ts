/**
 * Utility class for manage differentiation profiles page
 */

import { expect, type Page } from "@playwright/test";
import type AcceptanceTester from "@schoolboxTest/playwright/support/AcceptanceTester";
import AdminDifferentiationProfiles from "@schoolboxTest/playwright/support/page/admin/differentiation-profiles/AdminDifferentiationProfiles";
import ManageRecordSettings from "@schoolboxTest/playwright/support/page/pastoralCare/ManageRecordSettings";
import type DatabaseHelper from "../../DatabaseHelper";

export default class ManageDifferentiationProfiles {
    private tester: AcceptanceTester;

    private page: Page;

    static readonly DATA_TEST_TOGGLE = "differentiation-profiles-toggle";

    static readonly DATA_TEST_TERMINOLOGY_HEADER = "differentiation-profiles-header-Terminology";

    static readonly DATA_TEST_MODIFY_BUTTON = "lg-cell-action-save-modify-{studentId}-{fieldKey}";

    static readonly DATA_TEST_SAVE_BUTTON = "lg-cell-action-save-save-{studentId}-{fieldKey}";

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
     * @param db - Database helper instance
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

    /**
     * Initialize the page by enabling it, adding pastoral types, logging in as admin, and navigating to the page
     * @param db - Database helper instance
     */
    async initDefaults(db: DatabaseHelper): Promise<void> {
        await this.enablePage(db);
        await this.addPastoralTypes();
        await this.tester.loginAsAdmin();
        await this.navigateToPage();
    }

    /**
     * Reset the database to default state by disabling differentiation profiles and cleaning up related data
     * @param db - Database helper instance
     * @param studentId - Optional student ID to clean up differentiation profiles for
     */
    async resetDefaults(db: DatabaseHelper, studentId?: number): Promise<void> {
        this.tester.isGoingTo("Clean up: Reset data state");
        await db.updateInDatabase("config", { value: 0 }, { "name like": "differentiation_profiles_enabled" });
        await db.dontHaveInDatabase("userAccess", { uid: 1, service: 16 });
        await db.updateInDatabase(
            "lu_pastoral_type",
            { contribute_differentiation_profile: 0 },
            { "name like": "Bullying" },
        );
        await db.updateInDatabase(
            "lu_pastoral_subtype",
            { contribute_differentiation_profile: 0 },
            { "name like": "Award/Achievement" },
        );
        if (studentId !== undefined) {
            await db.dontHaveInDatabase("differentiation_profiles", { student_id: studentId });
        }
    }

    /**
     * Click a modify button
     * @param studentId - The ID of the student
     * @param fieldKey - The key of the field
     */
    async clickModifyButton(studentId: number, fieldKey: string): Promise<void> {
        await this.page
            .getByTestId(
                ManageDifferentiationProfiles.DATA_TEST_MODIFY_BUTTON.replace(
                    "{studentId}",
                    studentId.toString(),
                ).replace("{fieldKey}", fieldKey),
            )
            .click();
        await this.page.waitForTimeout(500); // Wait for the textbox to be focused
    }

    /**
     * Click a save button
     * @param studentId - The ID of the student
     * @param fieldKey - The key of the field
     */
    async clickSaveButton(studentId: number, fieldKey: string): Promise<void> {
        await this.page
            .getByTestId(
                ManageDifferentiationProfiles.DATA_TEST_SAVE_BUTTON.replace(
                    "{studentId}",
                    studentId.toString(),
                ).replace("{fieldKey}", fieldKey),
            )
            .click();
        this.tester.isGoingTo("Verify that the text is saved correctly");
        const successToast = this.page.getByText("Differentiation Profile successfully updated");
        await expect(successToast).toBeVisible();
    }
}
