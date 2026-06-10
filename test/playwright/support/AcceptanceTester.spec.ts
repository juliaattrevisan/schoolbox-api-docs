import { test, expect } from "@playwright/test";
import AcceptanceTester from "@schoolboxTest/playwright/support/AcceptanceTester";

test.describe("AcceptanceTester", () => {
    test("waits for a delayed What's New tour and closes it", async ({ page }) => {
        const tester = new AcceptanceTester(page);

        await page.setContent(`
            <button type="button" data-test="search-input-button">Search</button>
            <script>
                window.tourData = [{}];
                window.setTimeout(() => {
                    const overlay = document.createElement("div");
                    overlay.className = "introjs-overlay";
                    document.body.appendChild(overlay);

                    const buttons = document.createElement("div");
                    buttons.className = "introjs-tooltipbuttons";

                    const skipButton = document.createElement("a");
                    skipButton.className = "introjs-skipbutton";
                    skipButton.href = "#";
                    skipButton.textContent = "x";
                    skipButton.addEventListener("click", (event) => {
                        event.preventDefault();
                        overlay.remove();
                        buttons.remove();
                    });

                    buttons.appendChild(skipButton);
                    document.body.appendChild(buttons);
                    window.tourInserted = true;
                }, 150);
            </script>
        `);

        await tester.skipWhatsNewTour();
        await page.waitForFunction(() => {
            const tourWindow = window as Window & { tourInserted?: boolean };
            return tourWindow.tourInserted === true;
        });

        await expect(page.locator(".introjs-overlay")).toHaveCount(0);
    });
});
