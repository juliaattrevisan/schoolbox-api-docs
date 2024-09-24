import AcceptanceTester from "@schoolboxTest/playwright/support/AcceptanceTester";
import { Page } from "@playwright/test";

export default class Calendar {
    private tester: AcceptanceTester;

    private page: Page;

    static readonly URL = "/calendar/week";

    static readonly DAY_PANEL = "td.fc-day";

    static readonly CALENDAR_EVENT = "a.fc-daygrid-event";

    static readonly CALENDAR_PREVIOUS_BUTTON = "button.fc-customPrev-button.fc-button.fc-button-primary";

    static readonly EVENT_DIALOG = "div#createEventPanel";

    static readonly DIALOG_TITLE = "input#createEventBubbleTitle";

    static readonly DATA_TEST_DIALOG_CREATE_BUTTON = `create-event-modal-create-button`;

    static readonly BUTTON_LINK = "a.button";

    static readonly DATA_TEST_DIALOG_DELETE = "view-event-modal-delete-button";

    static readonly DATA_TEST_DELETE_CONFIRMATION_DIALOG = "event-delete-modal-confirm-delete-button";

    constructor(tester: AcceptanceTester, page: Page) {
        this.tester = tester;
        this.page = page;
    }

    /**
     * Navigates to the calendar page
     */
    async goto(): Promise<void> {
        await this.page.goto(Calendar.URL);
    }

    /**
     * click a day field in the calendar
     */
    async clickDayCalendar(): Promise<void> {
        await this.page.click(Calendar.DAY_PANEL);
    }

    /**
     * click previous button in the calendar
     */
    async clickPreviousButton(): Promise<void> {
        await this.page.click(Calendar.CALENDAR_PREVIOUS_BUTTON);
    }

    /**
     * click an event in the calendar grid
     * @param evenTitle - The event title
     */
    async clickCalendarEvent(evenTitle: string): Promise<void> {
        await this.page.click(`text=${evenTitle}`);
    }

    /**
     * DIALOG
     */

    /**
     * typing a title in the calendar dialog
     * @param dialogEventTitle - The dialog event title
     */
    async typeTitle(dialogEventTitle: string): Promise<void> {
        await this.page.waitForTimeout(1000);
        await this.page.locator(Calendar.DIALOG_TITLE).fill(dialogEventTitle);
    }

    /**
     * click Create button in the calendar dialog
     */
    async clickCreateEvent(): Promise<void> {
        await this.page.getByTestId(Calendar.DATA_TEST_DIALOG_CREATE_BUTTON).isEnabled();
        await this.page.getByTestId(Calendar.DATA_TEST_DIALOG_CREATE_BUTTON).click();
    }

    /**
     * click the button Delete the calendar event dialog
     */
    async clickDeleteDialogButton(): Promise<void> {
        await this.page.getByTestId(Calendar.DATA_TEST_DIALOG_DELETE).click();
    }

    /**
     * click the button Delete in the confirmation dialog
     */
    async clickConfirmDelete(): Promise<void> {
        await this.page.getByTestId(Calendar.DATA_TEST_DELETE_CONFIRMATION_DIALOG).click();
    }
}

// <?php

// /**
//  * @copyright   Copyright 2024, Schoolbox Pty Ltd
//  * @link        https://schoolbox.education/
//  */

// declare(strict_types=1);

// namespace Tests\Support\Page\Calendar;

// use Codeception\Util\Locator;
// use Exception;
// use Tests\Support\AcceptanceTester;

// class Calendar
// {
//     public const URL = '/calendar/week';

//     public const DAY_PANEL = 'td.fc-day';

//     public const CALENDAR_EVENT = 'a.fc-daygrid-event';

//     public const CALENDAR_PREVIOUS_BUTTON = 'button.fc-customPrev-button.fc-button.fc-button-primary';

//     public const EVENT_DIALOG = 'div#createEventPanel';

//     public const DIALOG_TITLE = 'input#createEventBubbleTitle';

//     public const DIALOG_CREATE_BUTTON = 'input[value=Create\ Event]';

//     public const BUTTON_LINK = 'a.button';

//     public const DELETE_CONFIRMATION_DIALOG = 'a.button.submit';

//     /**
//      * This test's acceptance tester actor.
//      */
//     private AcceptanceTester $tester;

//     /**
//      * Constructor
//      *
//      * @param AcceptanceTester $i
//      */
//     public function __construct(AcceptanceTester $i)
//     {
//         $this->tester = $i;
//     }

//     /**
//      * click a day field in the calendar
//      */
//     public function clickDayCalendar(): void
//     {
//         $this->tester->click(self::DAY_PANEL);
//     }

//     /**
//      * click previous button in the calendar
//      * @throws Exception
//      */
//     public function clickPreviousButton(): void
//     {
//         $this->tester->click(self::CALENDAR_PREVIOUS_BUTTON);
//     }

//     /**
//      * click an event in the calendar grid
//      * @throws Exception
//      */
//     public function clickCalendarEvent($evenTitle): void
//     {
//         $element = Locator::contains(self::CALENDAR_EVENT, $evenTitle);
//         $this->tester->clickWithLeftButton($element);
//     }
//     //DIALOG

//     /**
//      * typing a title in the calendar dialog
//      * @throws Exception
//      */
//     public function typeTitle($dialogEventTitle): void
//     {
//         $this->tester->fillField(self::DIALOG_TITLE, $dialogEventTitle);
//     }

//     /**
//      * click Create button in the calendar dialog
//      * @throws Exception
//      */
//     public function clickCreateEvent(): void
//     {
//         $this->tester->click(self::DIALOG_CREATE_BUTTON);
//     }

//     /**
//      * click the button View Details, Modify or Delete the calendar event dialog
//      * @throws Exception
//      */
//     public function clickDialogButton($buttonName): void
//     {
//         $element = Locator::contains(self::BUTTON_LINK, $buttonName);
//         $this->tester->clickWithLeftButton($element);
//     }

//     /**
//      * click the button Delete in the confirmation dialog
//      * @throws Exception
//      */
//     public function clickConfirmDelete(): void
//     {
//         $element = Locator::contains(self::BUTTON_LINK, 'Delete');
//         $this->tester->clickWithLeftButton(Locator::lastElement(self::DELETE_CONFIRMATION_DIALOG));
//     }
// }
// import AcceptanceTester from "@schoolboxTest/playwright/support/AcceptanceTester";
// import { Page, expect } from "@playwright/test";

// export default class Badges {
//     private tester: AcceptanceTester;

//     private page: Page;

//     static readonly URL = "/learning/badge";

//     static readonly BUTTON_ADD = "a.icon-add.button";

//     static readonly DROPDOWN_DELETE = "a.icon-delete";

//     static readonly BUTTON_CONFIRM_DELETE = "a.button.submit";

//     static readonly LIST_BOX_GOAL = "input[aria-controls='vs2__listbox']";

//     static readonly LIST_BOX_AWARD = "input[aria-controls='vs1__listbox']";

//     static readonly USER_DROPDOWN = "ul[class='vs__dropdown-menu']";

//     static readonly SELECTED_STUDENT = "span.d-center";

//     static readonly DATA_TEST_MODAL_BUTTON_SET_GOAL_ACHIEVEMENT = "badge-award-confirm";

//     constructor(tester: AcceptanceTester, page: Page) {
//         this.tester = tester;
//         this.page = page;
//     }

//     /**
//      * Navigates to the learning badges page
//      */
//     async goto(): Promise<void> {
//         await this.page.goto(Badges.URL);
//     }

//     /**
//      * wait Create Badge button to be presented
//      */

//     async waitAddBadgeButtonToBePresented(): Promise<void> {
//         await this.page.waitForSelector(Badges.BUTTON_ADD);
//     }

//     /**
//      * click Create Badge button
//      */
//     async clickAddBadgeButton(): Promise<void> {
//         await this.page.click(Badges.BUTTON_ADD);
//     }

//     /**
//      * verify badge name
//      * @param { string } text - The text to verify
//      */

//     async verifyTextIsPresented(text: string): Promise<void> {
//         await expect(this.page.locator("#container")).toContainText(text);
//     }

//     /**
//      * Click a badge dropdown
//      * @param badgeNumber - The badge number
//      */
//     async clickBadgeDropdown(badgeNumber: number): Promise<void> {
//         await this.page.getByTestId(`sbx-pop-out-badge-${badgeNumber}-dropdown`).click();
//     }

//     /**
//      * click an option in the dropdown (Award Achievement, Set Goal, etc)
//      * options are: achievement, goal, withdraw-achievement, close, modify, delete
//      * @param dropdownOption - The dropdown option
//      */
//     async clickDropdownOptionLastElement(dropdownOption: string): Promise<void> {
//         await this.page.click(`a.icon-${dropdownOption}`);
//     }

//     /**
//      * click an option in the dropdown (Award Achievement, Set Goal, etc)
//      * options are: achievement, goal, withdraw-achievement, close, modify, delete
//      * @param badgeId - The badge number
//      * @param dropdownOption - The dropdown option
//      */
//     async clickDropdownOptionFirstElement(badgeId: number, dropdownOption: string): Promise<void> {
//         await this.page.click(`#badge-${badgeId}-dropdown a.icon-${dropdownOption}`);
//     }

//     /**
//      * click an option Modify in the dropdown
//      * @param badgeNumber - The badge number
//      */
//     async clickDropdownModify(badgeNumber: number): Promise<void> {
//         await this.page.click(`a[href="/learning/badge/${badgeNumber}/update"]`);
//     }

//     /**
//      * click an option Delete in the dropdown
//      */
//     async clickDropdownDelete(): Promise<void> {
//         await this.page.click(Badges.DROPDOWN_DELETE);
//     }

//     /**
//      * click Delete button on confirmation dialog
//      */
//     async clickConfirm(): Promise<void> {
//         await this.page.locator(Badges.BUTTON_CONFIRM_DELETE).last().click();
//     }

//     /**
//      * search and select a student on goal the modal
//      * @param studentName - The student name
//      */
//     async selectStudentSetGoal(studentName: string): Promise<void> {
//         await this.page.fill(Badges.LIST_BOX_GOAL, studentName);
//         await this.page.waitForSelector(Badges.USER_DROPDOWN);
//         await this.page.press(Badges.LIST_BOX_GOAL, "Enter");
//         await this.page.click(Badges.SELECTED_STUDENT);
//         await this.page.keyboard.press("Tab");
//     }

//     /**
//      * search and select a student on goal the modal
//      * @param studentName - The student name
//      */
//     async selectStudentAwardAchievement(studentName: string): Promise<void> {
//         await this.page.fill(Badges.LIST_BOX_AWARD, studentName);
//         await this.page.waitForSelector(Badges.USER_DROPDOWN);
//         await this.page.press(Badges.LIST_BOX_AWARD, "Enter");
//         await this.page.click(Badges.SELECTED_STUDENT);
//         await this.page.keyboard.press("Tab");
//     }

//     /**
//      * click Set Goal on the modal
//      * @param set - The set to click
//      */
//     async clickSetGoalorAwardAchievement(set: string): Promise<void> {
//         await expect(
//             this.page.getByTestId(Badges.DATA_TEST_MODAL_BUTTON_SET_GOAL_ACHIEVEMENT).filter({ hasText: set }),
//         ).toBeEnabled();
//         await this.page
//             .getByTestId(Badges.DATA_TEST_MODAL_BUTTON_SET_GOAL_ACHIEVEMENT)
//             .filter({ hasText: set })
//             .click();
//     }

//     /**
//      * click submit button on the evidence page
//      */
//     async clickEvidenceSubmitButton(): Promise<void> {
//         await this.page.click("input[type=submit]");
//     }
// }
