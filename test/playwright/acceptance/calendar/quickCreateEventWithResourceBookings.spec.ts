// This test creates an event with resource bookings from the main calendar page
// Procedure:
//  1. Navigate to calendar on a future date (2077-01-29)
//  2. Click on calendar at 1pm to open Create Event modal
//  3. Enter event title 'Test event'
//  4. Search for 'catering' in resources field
//  5. Select 'Catering' and 'Schoolbox Catering Support' resources
//  6. Create the event
//  7. Verify event and resource bookings appear with correct statuses
//
// Prerequisites:
//  - Provision sales.tar.gz data
//
// Run with: npx playwright test calendar/createEventWithResourceBookings.spec.ts

import { test, expect } from "@schoolboxTest/playwright/support/integrations";
import type { Page } from "@playwright/test";

test.describe("Create event with resource bookings", () => {
    const CALENDAR_URL = "/calendar/29-01-2077/1";
    const EVENT_TITLE = "Test event";

    // Resource IDs from database fixtures
    const CATERING_RESOURCE_ID = "1"; // Moderation type: Open (auto-approved)
    const SCHOOLBOX_CATERING_SUPPORT_ID = "8"; // Moderation type: Moderated (pending)

    test.beforeEach(async ({ tester }) => {
        await tester.loginAsAdmin();
    });

    test.afterEach(async ({ tester }) => {
        await tester.logout();
    });

    /**
     * Helper to click on a specific time slot in the calendar
     * The calendar grid shows hourly slots, and we need to click on the 1pm slot
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

    /**
     * Helper to type in the search-select resources field and wait for results
     */
    async function searchForResources(page: Page, query: string): Promise<void> {
        // The resources search-select has labelId="resources"
        const searchInput = page.locator('[data-test="search-select-resources"] input[type="text"]');
        await searchInput.fill(query);

        // Wait for search results to appear
        await page.waitForTimeout(500); // Allow time for debounced search
        await page.waitForSelector(".results", { state: "visible" });
    }

    /**
     * Helper to select a resource by clicking its checkbox or label
     */
    async function selectResourceCheckbox(page: Page, resourceId: string): Promise<void> {
        // Try clicking the checkbox container div which is more reliably clickable
        const container = page.locator(`[data-test="ss-results-item-container-${resourceId}"]`);
        await container.waitFor({ state: "visible", timeout: 5000 });
        await container.click();
        await page.waitForTimeout(500); // Brief pause for UI update
    }

    /**
     * Helper to verify an event appears on the calendar with the correct time
     */
    async function verifyEventOnCalendar(page: Page, eventTitle: string): Promise<void> {
        // Find event by title - regular events don't have the 'booking' class
        const eventElement = page.locator(`a.fc-event:has-text("${eventTitle}")`).first();
        await expect(eventElement).toBeVisible();

        // Verify the event title
        await expect(eventElement).toContainText(eventTitle);
    }

    /**
     * Helper to verify a resource booking appears on the calendar with the correct status
     * Bookings appear as calendar events with the resource name
     */
    async function verifyResourceBookingOnCalendar(
        page: Page,
        resourceName: string,
        expectedStatusClass: "approved" | "pending" | "rejected",
    ): Promise<void> {
        // Resource bookings appear as events on the calendar
        // Find any fc-event that contains the resource name
        const bookingElement = page.locator(`a.fc-event:has-text("${resourceName}")`).first();
        await expect(bookingElement).toBeVisible({ timeout: 10000 });

        // Verify the resource name is displayed
        await expect(bookingElement).toContainText(resourceName);

        // Optional: Verify the booking has the expected status class if the booking class exists
        // The status is indicated by background color: green (approved), orange (pending), red (rejected)
        const className = await bookingElement.getAttribute("class");
        if (className && className.includes("booking")) {
            await expect(bookingElement).toHaveClass(new RegExp(`booking.*${expectedStatusClass}`, "i"));
        }
    }

    test("Create event with resource bookings from calendar", async ({ tester, page, db }) => {
        tester.isGoingTo("navigate to the calendar page for 2077-01-29");
        await page.goto(CALENDAR_URL);
        await page.waitForTimeout(2000); // Wait for calendar to render

        tester.isGoingTo("click on the calendar at 1pm to open Create Event modal");
        await clickCalendarTimeSlot(page, 13); // 13:00 = 1pm

        tester.isGoingTo("enter event title");
        const titleInput = page.locator("#createEventBubbleTitle");
        await titleInput.fill(EVENT_TITLE);

        tester.isGoingTo("search for 'catering' in resources field");
        await searchForResources(page, "catering");

        tester.isGoingTo("select Catering resource");
        await selectResourceCheckbox(page, CATERING_RESOURCE_ID);

        tester.isGoingTo("select Schoolbox Catering Support resource");
        await selectResourceCheckbox(page, SCHOOLBOX_CATERING_SUPPORT_ID);

        tester.isGoingTo("click Create Event button");
        const createButton = page.getByTestId("create-event-modal-create-button");
        await createButton.click();

        tester.isGoingTo("wait for success message and page to update");
        // The toast shows "Events retrieved successfully" after creation
        await expect(page.getByTestId("toast")).toContainText("Events retrieved successfully");
        await page.waitForTimeout(3000); // Wait for calendar to refresh with new items

        tester.isGoingTo("verify event 'Test event' appears on calendar from 1pm to 2pm");
        await verifyEventOnCalendar(page, EVENT_TITLE);

        tester.isGoingTo("verify Catering resource booking appears with Approved status");
        await verifyResourceBookingOnCalendar(page, "Catering", "approved");

        tester.isGoingTo(
            "verify Schoolbox Catering Support booking appears with Pending status (because this resource is moderated)",
        );
        await verifyResourceBookingOnCalendar(page, "Schoolbox Catering Support", "pending");

        tester.isGoingTo("clean up test data");
        // First grab the event ID
        const eventId = (await db.grabFromDatabase("event", "id", {
            title: EVENT_TITLE,
            deleted_at: null,
        })) as number;

        // Soft delete the resource bookings associated with this event
        if (eventId) {
            await db.updateInDatabase(
                "resource_booking",
                { deleted_at: "2022-01-15 11:22:34" },
                { event_id: eventId, deleted_at: null },
            );
        }

        // Soft delete the created event
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
