import { expect, type Locator, type Page } from "@playwright/test";
import type AcceptanceTester from "@schoolboxTest/playwright/support/AcceptanceTester";
import Homepage from "@schoolboxTest/playwright/support/page/homepage/Homepage";

export default class ClassListComponent {
    private page: Page;

    private tester: AcceptanceTester;

    private homepage: Homepage;

    // Data-test attribute for identifying student name element in the class list component
    static readonly DATA_TEST_STUDENT_NAME = "class-list-component-{userId}";

    // Data-test attribute for the Email Students button
    static readonly DATA_TEST_EMAIL_STUDENTS_BUTTON = "class-list-component-email-students";

    // Data-test attribute for the Email Parents button
    static readonly DATA_TEST_EMAIL_PARENTS_BUTTON = "class-list-component-email-parents";

    constructor(page: Page, tester: AcceptanceTester) {
        this.page = page;
        this.tester = tester;
        this.homepage = new Homepage(tester, page);
    }

    /**
     * Returns the locator for the Class List component on the page
     * @returns - The locator for the [data-component] element with heading "Class List"
     */
    locateComponent(): Locator {
        return this.homepage.locateComponentByName("Class List");
    }

    /**
     * Returns the name locator for a user in the class list component
     * @param userId - The user's id
     * @returns - The locator for the p[data-test] element
     */
    getStudentName(userId: number): Locator {
        return this.locateComponent().getByTestId(
            ClassListComponent.DATA_TEST_STUDENT_NAME.replace("{userId}", userId.toString()),
        );
    }

    /**
     * Returns the card container locator for a user in the class list component
     * @param userId - The user's id
     * @returns - The locator for the div.card element
     */
    getStudentCard(userId: number): Locator {
        return this.locateComponent().locator(
            `div.card:has([data-test="${ClassListComponent.DATA_TEST_STUDENT_NAME.replace("{userId}", userId.toString())}"])`,
        );
    }

    /**
     * Returns the photo image locator for a user in the class list component
     * @param userId - The user's id
     * @returns - The locator for the img element within the student card
     */
    getStudentPhoto(userId: number): Locator {
        return this.getStudentCard(userId).locator("img");
    }

    /**
     * Returns the meta paragraph locator (group) for a user in the class list component
     * @param userId - The user's id
     * @returns - The locator for the p.meta element within the student card
     */
    getStudentMeta(userId: number): Locator {
        return this.getStudentCard(userId).locator("p.meta");
    }

    /**
     * Returns the Email Students button locator
     * @returns - The locator for the [data-test="class-list-component-email-students"] element
     */
    getEmailStudentsButton(): Locator {
        return this.locateComponent().getByTestId(ClassListComponent.DATA_TEST_EMAIL_STUDENTS_BUTTON);
    }

    /**
     * Returns the Email Parents button locator
     * @returns - The locator for the [data-test="class-list-component-email-parents"] element
     */
    getEmailParentsButton(): Locator {
        return this.locateComponent().getByTestId(ClassListComponent.DATA_TEST_EMAIL_PARENTS_BUTTON);
    }

    /**
     * Checks that the Email Students and Email Parents buttons are visible with correct text
     */
    async checkEmailButtons(): Promise<void> {
        await expect(this.getEmailStudentsButton()).toBeVisible();
        await expect(this.getEmailStudentsButton()).toContainText("Email Students");
        await expect(this.getEmailParentsButton()).toBeVisible();
        await expect(this.getEmailParentsButton()).toContainText("Email Parents");
    }

    /**
     * Checks that the Email Students and Email Parents buttons are not visible
     */
    async checkEmailButtonsNotVisible(): Promise<void> {
        await expect(this.getEmailStudentsButton()).not.toBeVisible();
        await expect(this.getEmailParentsButton()).not.toBeVisible();
    }

    /**
     * Checks a student is presented in the class list component with their name, photo and meta
     * @param userId - The user's id
     * @param expectedName - The expected display name of the student
     */
    async checkStudentPresented(userId: number, expectedName: string): Promise<void> {
        await expect(this.getStudentName(userId)).toBeVisible();
        await expect(this.getStudentName(userId)).toContainText(expectedName);
        await expect(this.getStudentPhoto(userId)).toBeVisible();
        await expect(this.getStudentMeta(userId)).toBeVisible();
    }
}
