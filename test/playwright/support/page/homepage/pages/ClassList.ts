import { expect, type Locator, type Page } from "@playwright/test";
import type AcceptanceTester from "@schoolboxTest/playwright/support/AcceptanceTester";

export default class ClassList {
    private page: Page;

    private tester: AcceptanceTester;

    // URL for navigating to the class list page
    static readonly URL = "/learning/class/{classId}";

    static readonly DATA_TEST_STUDENT_PROFILE_FLAGS = "pastoral-flags-{studentId}";

    static readonly DATA_TEST_PROFILE_FLAG = "pastoral-flags-{typeId}-{subtypeId}";

    // Data test attribute for student username in class list
    static readonly DATA_TEST_STUDENT_USERNAME = "class-list-{studentId}-username";

    // Data test attribute for student year level in class list
    static readonly DATA_TEST_STUDENT_YEAR_LEVEL = "class-list-{studentId}-year-level";

    // Data test attribute for student house in class list
    static readonly DATA_TEST_STUDENT_HOUSE = "class-list-{studentId}-house";

    // Data test attribute for student last access time in class list
    static readonly DATA_TEST_STUDENT_LAST_ACCESS_TIME = "class-list-{studentId}-last-access-time";

    // Data test attribute for the 3-dot menu button for each student
    static readonly DATA_TEST_3_DOT_BUTTON = "sbx-pop-out-overflow{studentId}-button";

    // Data test attribute for 3-dot sub-menu options for each student
    static readonly DATA_TEST_MENU_OPTIONS = "class-list-{studentId}-menu-item-{option}";

    // Data test attribute for the add pastoral record button
    static readonly DATA_TEST_ADD_PASTORAL_BUTTON = "class-list-add-pastoral-record-button";

    // Data test attribute for the year level column header
    static readonly DATA_TEST_YEAR_LEVEL_COLUMN = "class-list-year-level-column";

    // Data test attribute for the house column header
    static readonly DATA_TEST_HOUSE_COLUMN = "class-list-house-column";

    constructor(page: Page, tester: AcceptanceTester) {
        this.page = page;
        this.tester = tester;
    }

    /**
     * Navigates to the class list page
     * @param classId - The class id
     */
    async goto(classId: number): Promise<void> {
        await this.page.goto(ClassList.URL.replace("{classId}", classId.toString()));
    }

    /**
     * Expect the class list url to be present, with the correct class id
     * @param classId - The class id
     */
    async expectClassListPage(classId: number): Promise<void> {
        await expect(this.page).toHaveURL(new RegExp(`\\/learning\\/class\\/${classId}(\\/)?$`));
    }

    /**
     * Returns a flag label locator
     * @param studentId - The student id
     * @param typeId - The pastoral type id
     * @param subtypeId - The pastoral subtype id
     * @returns - The locator for the flag label
     */
    getFlagLabel(studentId: number, typeId: number, subtypeId: number): Locator {
        const student = this.page.getByTestId(
            ClassList.DATA_TEST_STUDENT_PROFILE_FLAGS.replace("{studentId}", studentId.toString()),
        );
        return student.getByTestId(
            ClassList.DATA_TEST_PROFILE_FLAG.replace("{typeId}", typeId.toString()).replace(
                "{subtypeId}",
                subtypeId.toString(),
            ),
        );
    }

    /**
     * Returns the name link locator for a student row
     * @param studentId - The student id
     * @returns - The locator for the student name anchor element
     */
    getStudentNameLink(studentId: number): Locator {
        return this.page.getByTestId(ClassList.DATA_TEST_STUDENT_USERNAME.replace("{studentId}", studentId.toString()));
    }

    /**
     * Returns the name cell (TD) locator for a student row, which also contains the external ID
     * @param studentId - The student id
     * @returns - The locator for the TD containing the student name and external ID
     */
    getStudentNameCell(studentId: number): Locator {
        return this.page.locator(
            `td:has([data-test="${ClassList.DATA_TEST_STUDENT_USERNAME.replace("{studentId}", studentId.toString())}"])`,
        );
    }

    /**
     * Returns the year level cell locator for a student row
     * @param studentId - The student id
     * @returns - The locator for the year level TD element
     */
    getStudentYearLevel(studentId: number): Locator {
        return this.page.getByTestId(
            ClassList.DATA_TEST_STUDENT_YEAR_LEVEL.replace("{studentId}", studentId.toString()),
        );
    }

    /**
     * Returns the house cell locator for a student row
     * @param studentId - The student id
     * @returns - The locator for the house TD element
     */
    getStudentHouse(studentId: number): Locator {
        return this.page.getByTestId(ClassList.DATA_TEST_STUDENT_HOUSE.replace("{studentId}", studentId.toString()));
    }

    /**
     * Returns the last access time cell locator for a student row
     * @param studentId - The student id
     * @returns - The locator for the last access time TD element
     */
    getStudentLastAccessTime(studentId: number): Locator {
        return this.page.getByTestId(
            ClassList.DATA_TEST_STUDENT_LAST_ACCESS_TIME.replace("{studentId}", studentId.toString()),
        );
    }

    /**
     * Checks each column for a student row matches the expected values
     * @param studentId - The student id
     * @param expectedName - The expected name in lastname, firstname format
     * @param expectedExternalId - The expected external ID in parentheses
     * @param expectedYearLevel - The expected year level
     * @param expectedHouse - The expected house
     * @param expectedLastAccessTimeVisible - Whether the last access time is expected to be visible
     */
    async checkStudentRow(
        studentId: number,
        expectedName: string,
        expectedExternalId: string,
        expectedYearLevel: string,
        expectedHouse: string,
        expectedLastAccessTimeVisible: boolean,
    ): Promise<void> {
        await expect(this.getStudentNameLink(studentId)).toContainText(expectedName);
        await expect(this.getStudentNameCell(studentId)).toContainText(`(${expectedExternalId})`);
        await expect(this.getStudentYearLevel(studentId)).toContainText(expectedYearLevel);
        await expect(this.getStudentHouse(studentId)).toContainText(expectedHouse);
        if (expectedLastAccessTimeVisible) {
            await expect(this.getStudentLastAccessTime(studentId)).toBeVisible();
        } else {
            await expect(this.getStudentLastAccessTime(studentId)).toBeHidden();
        }
    }

    /**
     * click one or more checkboxes to select students
     * @param studentIds - The student ids to select
     */
    async selectStudents(studentIds: number[]): Promise<void> {
        // click select all to unselect all students before clicking the students we want to select for the tests
        await this.page.getByTestId("checkbox-select-all").click();
        for (const studentId of studentIds) {
            await this.page.getByTestId(`checkbox-${studentId}`).click();
        }
    }

    /**
     * select one or more checkboxes to select students
     * click one of the buttons: share learning moment, set goals, award achievement, add pastoral record, compose email
     * @param studentIds - The student ids to select before performing the action
     * @param action - The action to perform, e.g. "award achievement"
     */
    async performActionForSelectedStudents(
        studentIds: number[],
        action: "share learning moment" | "set goals" | "award achievement" | "add pastoral record" | "compose email",
    ): Promise<void> {
        await this.selectStudents(studentIds);
        let buttonLocator: Locator;
        switch (action) {
            case "share learning moment":
                buttonLocator = this.page.getByTestId("class-list-share-learning-moment-button");
                break;
            case "set goals":
                buttonLocator = this.page.getByTestId("class-list-set-goal-button");
                break;
            case "award achievement":
                buttonLocator = this.page.getByTestId("class-list-award-achievement-button");
                break;
            case "add pastoral record":
                buttonLocator = this.page.getByTestId(ClassList.DATA_TEST_ADD_PASTORAL_BUTTON);
                break;
            case "compose email":
                buttonLocator = this.page.getByTestId("class-list-compose-email-button");
                break;
            default:
                throw new Error(`Unknown action: ${action as string}`);
        }
        await buttonLocator.click();
    }

    /**
     * Check that all the menu items are presented under a student 3-dot button
     * @param studentId - The student id
     * @param expectedOptions - The expected options to be present in the menu: Due Work, Grades, ePortfolio, Pastoral Care, Calendar, Timetable
     * @param notExpectedOptions - The options that are not expected to be present in the menu
     */
    async check3DotMenuOptions(
        studentId: number,
        expectedOptions: string[],
        notExpectedOptions: string[] = [],
    ): Promise<void> {
        await this.page
            .getByTestId(ClassList.DATA_TEST_3_DOT_BUTTON.replace("{studentId}", studentId.toString()))
            .click();
        for (const option of expectedOptions) {
            await expect(
                this.page.getByTestId(
                    ClassList.DATA_TEST_MENU_OPTIONS.replace("{studentId}", studentId.toString()).replace(
                        "{option}",
                        option,
                    ),
                ),
            ).toBeVisible();
        }
        for (const option of notExpectedOptions) {
            await expect(
                this.page.getByTestId(
                    ClassList.DATA_TEST_MENU_OPTIONS.replace("{studentId}", studentId.toString()).replace(
                        "{option}",
                        option,
                    ),
                ),
            ).toBeHidden();
        }
    }

    /**
     * Checks that the 3-dot menu options for a student redirect to the correct page when clicked
     * @param classId - The class id, used to navigate to the class list page before clicking the option
     * @param studentId - The student id
     * @param option - The option to click: Due Work, Grades, ePortfolio, Pastoral Care, Calendar, Timetable
     * @param expectedUrlRegex - The regex that the url should match after clicking the option
     */
    async check3DotMenuOptionRedirect(
        classId: number,
        studentId: number,
        option: string,
        expectedUrlRegex: RegExp,
    ): Promise<void> {
        await this.goto(classId);
        await this.page
            .getByTestId(ClassList.DATA_TEST_3_DOT_BUTTON.replace("{studentId}", studentId.toString()))
            .click();
        await this.page
            .getByTestId(
                ClassList.DATA_TEST_MENU_OPTIONS.replace("{studentId}", studentId.toString()).replace(
                    "{option}",
                    option,
                ),
            )
            .click();
        await expect(this.page).toHaveURL(expectedUrlRegex);
    }

    /**
     * Checks the text in the add pastoral record button
     * @param expectedText - The expected text in the add pastoral record button
     */
    async checkAddPastoralRecordButtonText(expectedText: string): Promise<void> {
        await expect(this.page.getByTestId(ClassList.DATA_TEST_ADD_PASTORAL_BUTTON)).toHaveText(expectedText);
    }

    /**
     * Clicks the student 3-dot buttons
     * Check the sub menu text is correct for each one of the options: Due Work, Grades, Pastoral Care
     * @param studentId - The student id
     * @param optionTextMap - A map of the menu options and their expected text, e.g. { "Due Work": "Due Work", "Grades": "Grades", "Pastoral Care": "Pastoral Care" }
     */
    async check3DotMenuOptionsText(studentId: number, optionTextMap: Record<string, string>): Promise<void> {
        await this.page
            .getByTestId(ClassList.DATA_TEST_3_DOT_BUTTON.replace("{studentId}", studentId.toString()))
            .click();
        for (const [option, expectedText] of Object.entries(optionTextMap)) {
            await expect(
                this.page.getByTestId(
                    ClassList.DATA_TEST_MENU_OPTIONS.replace("{studentId}", studentId.toString()).replace(
                        "{option}",
                        option,
                    ),
                ),
            ).toHaveText(expectedText);
        }
    }

    /**
     * by passing year level or house it checks the column header text
     * or if both are passed it checks both column headers
     * @param expectedYearLevelColumnHeader - The expected year level column header text, e.g. "Year Level"
     * @param expectedHouseColumnHeader - The expected house column header text, e.g. "House"
     */
    async checkColumnHeaders(
        expectedYearLevelColumnHeader?: string,
        expectedHouseColumnHeader?: string,
    ): Promise<void> {
        if (expectedYearLevelColumnHeader) {
            await expect(this.page.getByTestId(ClassList.DATA_TEST_YEAR_LEVEL_COLUMN)).toHaveText(
                expectedYearLevelColumnHeader,
            );
        }
        if (expectedHouseColumnHeader) {
            await expect(this.page.getByTestId(ClassList.DATA_TEST_HOUSE_COLUMN)).toHaveText(expectedHouseColumnHeader);
        }
    }
}
