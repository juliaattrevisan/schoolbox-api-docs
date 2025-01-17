import { defineConfig, devices } from "@playwright/test";

/**
 * See https://playwright.dev/docs/test-configuration.
 */
export default defineConfig({
    testDir: "./test/playwright",
    /* Folder for test artifacts such as screenshots, videos, traces, etc.  */
    outputDir: "./test/playwright/_output/artifacts/",
    /* Do not run tests in parallel */
    fullyParallel: false,
    workers: 1,
    /* Fail the build on CI if you accidentally left test.only in the source code. */
    forbidOnly: !!process.env.CI,
    /* Retry on CI only */
    retries: process.env.CI ? 2 : 0,
    /* Timeout a test if it takes longer than 5 minutes - Value is in milliseconds */
    timeout: 5 * 60 * 1000,
    /* Reporter to use. See https://playwright.dev/docs/test-reporters */
    reporter: process.env.CI
        ? [
              ["html", { outputFolder: "./test/playwright/_output/html/", open: "never" }],
              ["list", { printSteps: true }],
          ]
        : [
              ["html", { outputFolder: "./test/playwright/_output/html/", port: 10081, host: "0.0.0.0" }],
              ["list", { printSteps: true }],
          ],
    /* Shared settings for all the projects below. See https://playwright.dev/docs/api/class-testoptions. */
    use: {
        /* Base URL to use in actions like `await page.goto('/')`. */
        baseURL: "https://vagrant.dev.schoolbox.cloud",

        /* Collect trace when retrying the failed test. See https://playwright.dev/docs/trace-viewer */
        trace: "retain-on-failure",

        /* Use data-test="" as a custom test id attribute. */
        testIdAttribute: "data-test",

        /* Take a screenshot whenever a test fails. */
        screenshot: "only-on-failure",

        /* Retain the video recordings for failed tests. */
        video: "retain-on-failure",

        /* Set viewport size */
        viewport: { width: 1920, height: 1080 },

        /* Set the default timeout for all the actions to be 10 seconds - Value is in milliseconds */
        actionTimeout: 15 * 1000,

        /* Set the default timeout for page.goto to be 30 seconds - Value is in milliseconds */
        navigationTimeout: 30 * 1000,

        /* Ignore HTTPS errors when on CI */
        ignoreHTTPSErrors: process.env.CI ? true : undefined,

        /* Make sure the timezone is right */
        timezoneId: "Australia/Melbourne",
    },
    expect: {
        /* Set the default timeout for all the expect assertions to be 10 seconds - Value is in milliseconds */
        timeout: 15 * 1000,
    },
    /* Configure projects for major browsers */
    projects: [
        {
            name: "chromium",
            use: {
                ...devices["Desktop Chrome"],
                launchOptions: {
                    args: [
                        "--disable-gpu",
                        "--disable-dev-shm-usage",
                        "--disable-web-security",
                        "--use-fake-ui-for-media-stream",
                        "--use-fake-device-for-media-stream",
                    ],
                },
            },
        },
        // {
        //     name: "firefox",
        //     use: { ...devices["Desktop Firefox"] },
        // },
        // {
        //     name: "webkit",
        //     use: { ...devices["Desktop Safari"] },
        // },

        /* Test against mobile viewports. */
        // {
        //   name: 'Mobile Chrome',
        //   use: { ...devices['Pixel 5'] },
        // },
        // {
        //   name: 'Mobile Safari',
        //   use: { ...devices['iPhone 12'] },
        // },

        /* Test against branded browsers. */
        // {
        //   name: 'Microsoft Edge',
        //   use: { ...devices['Desktop Edge'], channel: 'msedge' },
        // },
        // {
        //   name: 'Google Chrome',
        //   use: { ...devices['Desktop Chrome'], channel: 'chrome' },
        // },
    ],
});
