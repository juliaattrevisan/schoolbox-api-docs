// This test creates a quick event, then adds and removes resource bookings on the event details page
// Procedure:
//  1. Navigate to calendar on a future date (2076-01-30)
//  2. Click on calendar at 1pm to open Create Event modal
//  3. Enter event title 'A'
//  4. Create the event
//  5. View event details
//  6. Add resource bookings from the event details page
//  7. Remove a resource booking
//  8. Add another resource booking
//
// Prerequisites:
//  - Provision sales.tar.gz data
//
// Run with: npx playwright test calendar/quickCreateEventThenAddAndRemoveBookingsOnEventDetailsPage.spec.ts

import { test, expect } from "@schoolboxTest/playwright/support/integrations";
import type { Page } from "@playwright/test";

test.describe("Quick create event then add and remove bookings on event details page", () => {
    const CALENDAR_URL = "/calendar/30-01-2076/1";
    const EVENT_TITLE = "A";

    test.beforeEach(async ({ tester }) => {
        await tester.loginAsAdmin();
    });

    test.afterEach(async ({ tester }) => {
        await tester.logout();
    });

    /**
     * Helper to click on a specific time slot in the calendar
     * The calendar grid shows hourly slots, and we need to click on the 1pm slot
     * @param page - Playwright page object
     * @param hour - Hour to click (24-hour format, e.g., 13 for 1pm)
     */
    async function clickCalendarTimeSlot(page: Page, hour: number): Promise<void> {
        // Wait for calendar to be fully loaded with FullCalendar initialized
        await page.waitForFunction(
            () => {
                return Object.hasOwn(window, "sbProps") && document.querySelector(".fc") !== null;
            },
            { timeout: 10000 },
        );

        // Ensure we're in a time-grid view (Day view) to see hourly slots
        await page.click("button.fc-timeGridDay-button");
        await page.waitForTimeout(2000); // Wait for view to switch and re-render

        // FullCalendar uses data-time attribute for time slots in time-grid view
        // Format is "HH:00:00" for the hour slot
        const timeString = `${hour.toString().padStart(2, "0")}:00:00`;

        // Find the time slot - the data-time is on the slot itself (.fc-timegrid-slot)
        // Then we need to click on the corresponding column area (.fc-timegrid-col)
        const timeSlot = page.locator(`.fc-timegrid-slot[data-time="${timeString}"]`).first();
        await timeSlot.waitFor({ state: "visible", timeout: 10000 });

        // Get the bounding box and click in the main content area to the right
        const box = await timeSlot.boundingBox();
        if (box) {
            // Click to the right of the time label, in the actual calendar grid area
            await page.mouse.click(box.x + box.width + 100, box.y + box.height / 2);
        }

        // Wait for the create event modal to appear
        await page.waitForSelector(".reveal-modal.open", { timeout: 5000 });
        await page.waitForTimeout(500); // Additional wait for modal animation
    }

    test("Create event and manage resource bookings from event details page", async ({ tester, page, db }) => {
        tester.isGoingTo("clean up any existing test data before starting");
        // Clean up any existing events with this title
        await db.updateInDatabase(
            "event",
            { deleted_at: "2022-01-15 11:22:34" },
            { title: EVENT_TITLE, deleted_at: null },
        );

        tester.isGoingTo("navigate to the calendar page for 2076-01-30");
        await page.goto(CALENDAR_URL);
        await page.waitForTimeout(2000); // Wait for calendar to render

        tester.isGoingTo("click on the calendar at 1pm to open Create Event modal");
        await clickCalendarTimeSlot(page, 13); // 13:00 = 1pm

        tester.isGoingTo("enter event title");
        const titleInput = page.locator("#createEventBubbleTitle");
        await titleInput.fill(EVENT_TITLE);

        tester.isGoingTo("click Create Event button");
        const createButton = page.getByTestId("create-event-modal-create-button");
        await createButton.click();

        tester.isGoingTo("wait for success message and page to update");
        await expect(page.getByTestId("toast")).toContainText("Events retrieved successfully");
        await page.waitForTimeout(3000); // Wait for calendar to refresh with new event

        tester.isGoingTo("click on the event to view details");
        const eventOnCalendar = page.locator(`a.fc-event:has-text("${EVENT_TITLE}")`).first();
        await eventOnCalendar.click();
        await page.waitForTimeout(500); // Wait for event popup

        tester.isGoingTo("click VIEW DETAILS button");
        const viewDetailsButton = page.getByText("VIEW DETAILS");
        await viewDetailsButton.click();
        await page.waitForTimeout(1000); // Wait for details page to load

        tester.isGoingTo("click on Book resources for this event link");
        const bookResourcesLink = page.getByText("Book resources for this event");
        await bookResourcesLink.click();
        await page.waitForTimeout(500); // Wait for modal to appear

        tester.isGoingTo("verify Add Resource Bookings modal is open");
        await expect(page.getByText("Add Resource Bookings")).toBeVisible();

        tester.isGoingTo("search for resources with 'oo'");
        const resourceSearchInput = page.locator('input[type="text"]').last();
        await resourceSearchInput.fill("oo");
        await page.waitForTimeout(1000); // Wait for search results

        tester.isGoingTo("select Schoolbox Additional Furniture");
        await page.getByText("Schoolbox Additional Furniture").click();
        await page.waitForTimeout(300);

        tester.isGoingTo("select Schoolbox After Hours Security");
        await page.getByText("Schoolbox After Hours Security").click();
        await page.waitForTimeout(300);

        tester.isGoingTo("select Schoolbox Board House Extension");
        await page.getByText("Schoolbox Board House Extension").click();
        await page.waitForTimeout(300);

        tester.isGoingTo("select Schoolbox Board Meeting Room");
        await page.getByText("Schoolbox Board Meeting Room").click();
        await page.waitForTimeout(300);

        tester.isGoingTo("submit the resource bookings");
        const submitButton = page.getByRole("button", { name: "Submit" });
        await submitButton.click();
        await page.waitForTimeout(2000); // Wait for bookings to be created

        tester.isGoingTo("verify resource bookings appear on event details page with correct statuses");
        await expect(page.getByText("Schoolbox Additional Furniture")).toBeVisible();
        await expect(page.getByText("Schoolbox After Hours Security")).toBeVisible();
        await expect(page.getByText("Schoolbox Board House Extension")).toBeVisible();
        await expect(page.getByText("Schoolbox Board Meeting Room")).toBeVisible();

        tester.isGoingTo("verify Schoolbox Additional Furniture has APPROVED status");
        const additionalFurnitureRow = page.locator('text="Schoolbox Additional Furniture"').locator("..");
        await expect(additionalFurnitureRow.locator(".label.approved")).toBeVisible();

        tester.isGoingTo("verify Schoolbox After Hours Security has PENDING status");
        const afterHoursRow = page.locator('text="Schoolbox After Hours Security"').locator("..");
        await expect(afterHoursRow.locator(".label.pending")).toBeVisible();

        tester.isGoingTo("verify Schoolbox Board House Extension has PENDING status");
        const boardHouseRow = page.locator('text="Schoolbox Board House Extension"').locator("..");
        await expect(boardHouseRow.locator(".label.pending")).toBeVisible();

        tester.isGoingTo("verify Schoolbox Board Meeting Room has PENDING status");
        const boardMeetingRow = page.locator('text="Schoolbox Board Meeting Room"').locator("..");
        await expect(boardMeetingRow.locator(".label.pending")).toBeVisible();

        tester.isGoingTo("remove Schoolbox Board Meeting Room booking");
        // Find the modify icon next to Board Meeting Room and click it
        const boardMeetingRoomRow = page.locator('text="Schoolbox Board Meeting Room"').locator("..");
        const modifyIcon = boardMeetingRoomRow.locator('.icon-modify').first();
        await modifyIcon.click();
        await page.waitForTimeout(500); // Wait for edit form to slide down

        tester.isGoingTo("click Delete button in the edit form");
        const deleteButton = page.getByRole("button", { name: "Delete" });
        await deleteButton.click();
        await page.waitForTimeout(2000); // Wait for deletion to complete

        tester.isGoingTo("verify Schoolbox Board Meeting Room booking is removed");
        await expect(page.getByText("Schoolbox Board Meeting Room")).not.toBeVisible();

        tester.isGoingTo("add Schoolbox Chapel Audio Visual Support booking");
        // Click the + button to add more bookings
        const addBookingButton = page.locator('.icon-add').first();
        await addBookingButton.click();
        await page.waitForTimeout(500);

        tester.isGoingTo("search for Chapel Audio Visual Support");
        const searchInput2 = page.locator('input[type="text"]').last();
        await searchInput2.fill("chapel");
        await page.waitForTimeout(1000);

        tester.isGoingTo("select Schoolbox Chapel Audio Visual Support");
        await page.getByText("Schoolbox Chapel Audio Visual Support").click();
        await page.waitForTimeout(300);

        tester.isGoingTo("submit the new booking");
        const submitButton2 = page.getByRole("button", { name: "Submit" });
        await submitButton2.click();
        await page.waitForTimeout(2000);

        tester.isGoingTo("verify Schoolbox Chapel Audio Visual Support booking appears with APPROVED status");
        await expect(page.getByText("Schoolbox Chapel Audio Visual Support")).toBeVisible();
        const chapelRow = page.locator('text="Schoolbox Chapel Audio Visual Support"').locator("..");
        await expect(chapelRow.locator(".label.approved")).toBeVisible();

        tester.isGoingTo("clean up test data");
        // Get the event ID
        const eventId = (await db.grabFromDatabase("event", "id", {
            title: EVENT_TITLE,
            deleted_at: null,
        })) as number;

        // Soft delete the resource bookings
        if (eventId) {
            await db.updateInDatabase(
                "resource_booking",
                { deleted_at: "2022-01-15 11:22:34" },
                { event_id: eventId, deleted_at: null },
            );
        }

        // Soft delete the event
        await db.updateInDatabase(
            "event",
            { deleted_at: "2022-01-15 11:22:34" },
            { title: EVENT_TITLE, deleted_at: null },
        );
    });

    test.afterAll(async ({ baseTester, db }) => {
        baseTester.isGoingTo("ensure all test data is cleaned up");

        // Final cleanup - try to grab event ID in case previous cleanup failed
        const eventId = (await db
            .grabFromDatabase("event", "id", {
                title: EVENT_TITLE,
                deleted_at: null,
            })
            .catch(() => null)) as number | null;

        // Clean up resource bookings if event exists
        if (eventId) {
            await db.updateInDatabase(
                "resource_booking",
                { deleted_at: "2022-01-15 11:22:34" },
                { event_id: eventId, deleted_at: null },
            );
        }

        // Final cleanup - ensure event is deleted
        await db.updateInDatabase(
            "event",
            { deleted_at: "2022-01-15 11:22:34" },
            { title: EVENT_TITLE, deleted_at: null },
        );
    });
});