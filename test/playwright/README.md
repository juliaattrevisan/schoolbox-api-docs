# Playwright Tests

All Playwright tests go in this folder.


## Structure
```sh
./_output/ # Logs, screenshots, and other artifacts of runs
./_support/ # Custom steps and page logic helpers for tests
./aceptance/**/*.spec.ts # All acceptance tests scenarios
```

## Install dependencies

At the moment, the tools used by Playwright are not initially installed inside vagrant or your device. Before running tests, run:
```sh
# If in vagrant, cd /usr/share/schoolbox
# If outside of vagrant, cd /path/to/repo/schoolbox

npx playwright install
```

Playwright will warn you if you do not have the required tools installed yet.

## Running Tests

Unlike codeception, these tests do not need be run run inside the vagrant box, but they can be if you want to.

### 1. Command Line
To run all tests, ensure you are in the schoolbox directory (playwright.config.ts is)
```sh
npx playwright test
```

To run a specific test, or a set of tests, add the name or directory of the test(s) you want to run:
```sh
# a single test
npx playwright test acceptance/unitPlanner/unitPlanner.spec.ts
# or
npx playwright test unitPlanner.spec.ts

# multiple tests
npx playwright test acceptance/dueWork/ acceptance/learningMoments/
```

When tests finish, Playwright will create a webserver on port `10081` and provide a [full report]

### 2. Run tests in Visual Studio Code
You can also run tests right from VSCode using the [Playwright Test for VSCode](https://marketplace.visualstudio.com/items?itemName=ms-playwright.playwright) extension by Microsoft

Once Installed, you can open any `.spec.ts` test file and click the green triangle on the left sidebar next to any test or suite of tests you want to run. See Playwright's [Getting Started - VS Code](https://playwright.dev/docs/getting-started-vscode#running-tests) guide for more details.

I recommend turning on the "Show browser" option under the testing sidebar so you can watch Playwright running the tests in real time. The extension also provides tools to pick locators on the page and record actions, which save time while writing tests.


### 3. Running Tests in UI mode
When running the tests locally (not inside vagrant), you can also use Playwright's [UI Mode](https://playwright.dev/docs/test-ui-mode), which is incredibly helpful to see everything that is happening during a test, and find what went wrong when debugging issues.

To start this mode, just run:
```sh
npx playwright test --ui
```

## Debugging Tests
You can easily debug tests using any of the methods above, each provide a different experience for debugging

### 1. Debugging tests from the HTML report
This can help provide a lot of insight into what went wrong after running tests from the command line. \
See [Viewing the HTML Report](#viewing-the-html-report) below for more details on how to access the HTML report.

### 2. Debugging tests in Visual Studio Code
When running tests in VSCode, you can set breakpoints in your test files and run them in debug mode. \
This will allow you to step through the test line-by-line and see what is happening at each step, including the browser's state and any variables in the code.

To run a test in debug mode:
1. Open a `.spec.ts` test file
2. Set breakpoints by clicking on the left side of the line number you want to stop at
3. Right-click the green triangle on the left sidebar (or red cross if the test failed) and select "Debug Test"
4. The test will run and stop at the first breakpoint, allowing you to step through the test

### 3. Debugging tests in UI mode
You can also use [UI Mode](https://playwright.dev/docs/test-ui-mode) to walk through each step of the test and visually see what playwright is doing.

This mode also provides access to the browser's JavaScript console and other request information, which can be helpful for debugging issues with the page itself.

## Viewing the HTML Report
After running tests, playwright will generate a HTML report that you can access in your browser and see:
- Which tests were failed or succeeded
- All steps taken and the output of each test
- Screenshots and videos for any failed tests
- A detailed trace for each failed test, similar to UI mode

If errors occur while running `npx playwright test`, this web server will be started automatically on port `10081`

You can start the web server manually by running
```sh
npx playwright show-report test/playwright/_output/html --port 10081 --host 0.0.0.0
```

Then access the report:
- If running locally, visit http://0.0.0.0:10081
- If running inside vagrant, visit http://[vagrant-ip]:10081, e.g. http://192.168.36.10:10081. \
    You can also use http://vagrant.dev.schoolbox.cloud:10081 from a private window, but as the webserver is HTTP-only, HSTS may cause issues if you have visited any other vagrant link previously, so I recommend just using the IP directly if possible.

## Writing tests

See `test/playwright/acceptance/login.spec.ts` for an example - it shows an example of
driving a web browser to perform commands, and of a direct database query.

### Guidelines

I've taken a similar approach to the structure of Playwright tests as we have with Codeception tests.

Now that tests are written in TypeScript, we can take advantage of type checking and general best practices that are already followed in other TS files around the system. \
See https://schoolbox.atlassian.net/wiki/spaces/DEV/pages/1825406996/TypeScript on Confluence for more information on how we use TypeScript already. The same principles apply to these tests.

ESLint is also enabled for these tests, so you will see warnings and errors in the editor if there are any issues with the code. I also recommend ensuring Prettier is enabled in your editor to keep the code consistent between developers.

#### File naming conventions
When writing tests, the file name should be the name of the feature being tested, followed by `.spec.ts`. \
For example, if you are testing the login functionality, the file could be named `login.spec.ts`.

Test files should use camelCase for the file name, (e.g. `searchPage.spec.ts`) and should be placed in the `acceptance` directory.\

Page support files should be placed in the `support/page` directory, and use PascalCase for the file name as they export default a class (e.g. `LoginPage.ts`).

We currently use the airbnb style guide for TypeScript files, you can read more about it at https://github.com/airbnb/javascript

#### Using safe selectors

Playwright tests, in their simplest form, are a list of actions that take place in a browser:

```ts
test("login with an invalid username and password", async ({ page }) => {
    await page.goto("/login");
    await page.locator("input[name='username']").fill("alaress");
    await page.locator("input[name='password']").fill("wrong");
    await page.locator("label[for='rememberme']").check();
    await page.locator("button[type='submit']").click();
    await expect(page.locator("#error-msg")).toHaveText(
        "The username or password you typed is incorrect, please try again.",
    );
});
```

However, simply defining a test as a list of actions has issues:
* it doesn't really document what the end user is actually doing, from their perspective
* its intent isn't clear from reading; what is the user actually modifying?
* it's fragile; what happens if the page's design changes?
* how do you know there is only one of each specific element on the page, what if there are two submit buttons?

> If a selector for an element that a test needs to find is complicated, this
> is a sign that either the page's layout needs to be simplified, or the selector needs to be simplified

It isn't recommended to find elements by their CSS selector, as this is fragile and can break easily. \
Instead, use data-test attributes on elements to make them easier to find. Using these attributes also identifies to developers that the element is used for testing purposes, and changing it may break tests.

Playwright also has various other methods to find elements on a page, you can see them in the [Playwright documentation](https://playwright.dev/docs/locators).

As mentioned above, our preferred method is to use `.getByTestId()` to find elements on the page, rewriting the above example, we could get something like:

```ts
test("login with an invalid username and password", async ({ page }) => {
    await page.goto("/login");
    await page.getByTestId("login-username").fill("alaress");
    await page.getByTestId("login-password").fill("wrong");
    await page.getByText("Remember me").check();
    await page.getByTestId("login-submit").click();
    await expect(page.getByTestId("login-error-msg")).toHaveText(
        "The username or password you typed is incorrect, please try again.",
    );
});
```

This is much more readable and understandable, and if the page's layout changes, the test will still work as long as the data-test attributes are still present.

#### Using Page Objects

As tests get larger and more specific page interactions are needed, it is recommended to create [page objects](https://playwright.dev/docs/pom) for each type of page for which a test is being written.

These page objects can contain all the methods needed to interact with the page, and the test can call these methods instead of directly interacting with the page. This makes the tests much more readable and maintainable.

If multiple tests need to interact with the same page, this page object is then a single source of truth for how to interact with that page, and if the page changes, only the page object needs to be updated, instead of finding and updating every test that interacts with that page.

We keep these page objects in the `test/playwright/support/page` directory, and they should be named after the page they represent, e.g. `LoginPage.ts`.

You can find an example of a page object in `test/playwright/support/page/LoginPage.ts`. \
Rewriting the previous test now, as well as adding an additional test, we get:
```ts
import { test, expect } from "@schoolboxTest/playwright/support/integrations";
import LoginPage from "@schoolboxTest/playwright/support/page/LoginPage";

test.describe("Login functionality tests", () => {
    let loginPage: LoginPage;

    test.beforeEach(async ({ page }) => {
        loginPage = new LoginPage(page);
        await loginPage.goto();
    });

    test("login successfully", async () => {
        await loginPage.login("alaress", "alaress", true);
        await loginPage.verifyLoginSuccess();
    });

    test("login with an invalid username and password", async () => {
        await loginPage.login("alaress", "wrong", true);
        await loginPage.verifyLoginFailure();
    });
});
```

Notice that we can use the loginPage.login() method in multiple tests now, and if the login form changes, we only need to update the loginPage spec, not every test that uses it.

#### Document intent, not just actions

When writing tests, it is important to document not just the actions that are being taken, but also the intent of the test. This can help identify what the test is doing, and why it is doing it.

Let's use `searchPage.spec.ts` as an example:
```ts
// ...
test("Filter search by type news and verify results", async () => {
    await searchPage.inputKeyword("vce");
    await searchPage.clearFilters();
    await searchPage.clickFilterType("news");
    await searchPage.clickApplyFilters();
    await searchPage.verifyResultsCount(4);
});
```

This is a good start, but it doesn't really tell us what the test is doing, just the actions it is taking. We can add some context to the test by using `tester.isGoingTo()`:
```ts
// ...
test("Filter search by type news and verify results", async ({ tester }) => {
    tester.isGoingTo("input a valid keyword, clear filters, and click the news filter type");
    await searchPage.inputKeyword("vce");
    await searchPage.clearFilters();
    await searchPage.clickFilterType("news");

    tester.isGoingTo("click the apply filters button and expect to see 4 results");
    await searchPage.clickApplyFilters();
    await searchPage.verifyResultsCount(4);
});
```

When reading the test, it is now clear that the test is entering a known valid keyword, instead of an arbitrary one, it also helps identify the intent of the test, and what the expected outcome is.

This can be especially helpful in much longer tests, where we may start to lose track of why the test is taking specific actions.

#### Integrations (Playwright Fixtures)

You'll notice that in the above example, a `tester` was added to the test function.

Playwright allows us to add [fixtures](https://playwright.dev/docs/test-fixtures) to our tests, which can provide additional functionality and helper methods to our tests, without having to duplicate code. \
We have named these **"integrations"** instead to avoid confusion with our own Fixture system.

In the above example, `tester` is a custom fixture, from `AcceptanceTester.ts`, which provides various methods used across many tests. This includes things like logging in, impersonating a user, filling in a CKEditor, or other common actions like the isGoingTo() method.

The available integrations are defined in `integrations.ts`, and can be added to any test by adding them to the test function parameters.

**Custom integrations:**
We have a few custom integrations, these are:
- `db` - Provides access to the database for the test
- `tester` - Provides common test actions that require access to the page object. Has all methods of `baseTester`
- `baseTester` - Provides common test actions, but does not require access to the page object

`baseTester` allows you to perform actions while not in the right context for a page, such as inside the `afterAll` hook.

These integrations can be passed in like any [built-in integration](https://playwright.dev/docs/test-fixtures), for example, if we wanted access to the database:
```ts
// ...
test("login with an invalid username and password", async ({ tester, db }) => {
    await loginPage.login("wrong", "wrong", true);
    await loginPage.verifyLoginFailure();

    tester.isGoingTo("check the database to ensure that no user with the username 'wrong' exists");
    const count = await db.countInDatabase("user", { username: "wrong" });
    expect(count).toBe(0);
});
```

### General tips

- **Use `test.beforeEach` and `test.afterEach` hooks to set up and tear down the test environment**
    - For example, this can be used to log in before each test

- **Keep tests isolated and independent**
    - Ideally, each test should be able to run on its own, without relying on the state of other tests
    - In practice, this isn't always possible. If a suite of tests relies on prior tests, add `test.describe.configure`
    ```ts
    test.describe("Initial Due Work functionality tests", () => {
        // Run tests serially, as tests rely on prior tests to have been run
        test.describe.configure({ mode: "serial" });
    ```
    - This will ensure that tests run in the order they are defined, and if one fails, the rest will not run

- **Use `test.afterAll` to clean up after all tests**
    - This can be used to log out after all tests have run, or to clean up any data that was created during the tests

- **Ensure tests can run multiple times without affecting the outcome**
    - This means that if a test creates data, it should clean up after itself, so that the next run of the test doesn't fail because the data already exists

- **Try to keep tests as short and focused as possible**
    - If a test is doing too much, consider breaking it up into multiple tests
    - This makes it easier to identify what is failing, and why

- **Try to use .getByTestId() as much as possible**
    - This makes tests more resilient to changes in the page layout, and makes it easier to identify elements on the page
    - If a `data-test` selector is not available, consider adding one

- **Split page interaction logic into page objects**
    - This makes tests more readable, reusable, and easier to maintain
    - If a page changes, only the page object needs to be updated, not every test that interacts with that page
