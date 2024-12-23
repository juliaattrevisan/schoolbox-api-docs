# Integration and Smoke Tests

All Codeception integration tests go in this folder. Smoke tests also live here.

## Structure

Same as the structure in smoke tests, you can see it here:
[Smoke Tests](../Smoketests/README.md).

Smoke tests currently live in SmokeTests/

## Running tests

All tests run with a PHP browser, CLI module and DB connection modules.
The PHPbrowser is basically a webdriver without the front end. So it cannot test java.

The smoke tests do not even hit the PHP browser interface they instead use the curl client underneath.
This is to increase the speed of tests.

### Configuration

Same as smoke tests as they are all based on the same framework.
Found in documentation here: [Smoke Tests](../Smoketests/README.md).

### What is covered by the smoke tests

Currently at the moment (13/11/2019) this is what is covered by the tests. For this list to be relevant we will need to keep it up to date.

* Get requests
    * full list of URLs being tested and combinations are in this csv: [./_data/SmokeTests/GetSmokeTestList.csv](../_data/SmokeTests/GetSmokeTestList.csv)
* Group and Homepage Assessment
    * Create assessment with no self reflection, no online submission, published, weight >0, use rubric true
       * Create
       * Update
       * Submit on behalf of student
       * Mark
    * Create assessment with no self reflection, no online submission, published, weight >0, use rubric false
       * Create
       * Update
       * Submit on behalf of student
       * Mark
* Calendar
    * On full calendar month view create event set to happen today
       * Personal event (through modal)
       * Personal event
       * Group event
       * Campus event
    * Modify personal event
    * RSVP to campus event
    * Approve attendees of campus event
* Course
    * Course with a project with a due work and a due work
       * Create course
       * Import course into group page
    * Course with empty unit
       * Create course
* Home Page
    * Create folder
    * Create folder with curriculum tagging off
    * Drag drop file
       * Homepage upload file
       * Resource upload file
       * Storage upload
    * Post file upload file form
    * Homepage
       * Set up users
       * Set up text component
* Login Page
    * Login Local User
    * Forgot password form
* News
    * Draft News
       * Create
       * Update
       * Submit to moderation
       * Publish
    * Moderation news
       * Accept news
       * Reject news
    * Published news
       * Saves news for later
       * Delete news
* Pastoral
    * Pastoral record
       * Create
       * Modify
       * Duplicate
       * Comment Private
       * Comment Public

### Codeception test

To run integration tests, like acceptance tests, you call codeception,
select the suite to be integration and then select the test you are after.

So to run all the integration tests:

    vagrant ssh
    cd /usr/share/schoolbox
    ./vendor/bin/codecept run --steps integration

To run a specific test:

    ./vendor/bin/codecept run --steps integration LoginCest.php

To run all the smoke tests you would run an integration test and point the tester to run all the tests inside the smoke test folder.

    ./vendor/bin/codecept run --steps integration SmokeTests

## Writing tests

#### Integration Tests

None as of yet.

#### Smoke Tests

See [test/codeception/integration/SmokeTests/LoginCest.php](SmokeTests/LoginCest.php) for an example - it shows an example of a simple creation of a payload and then firing it off at the schoolbox endpoint.

### Guidelines

#### Document intent, not just actions

###### Integration Tests

Document the test based on intent of the user, each action or attempted change on the system should read
as the person sending sepperate requests for changes to be made on the system.

So like with acceptance tests actor and helpers make the intent clear.

eg.

        $i->Login("alaress", "alaress");
        $i->CreateAUser("This test guy", "student");
        $student=$SchoolboxHelper->getFirstStudentByUsername("This test guy");
        ... ect ...

###### Smoke Tests

A little different from the Acceptance and Integration testing as the actions are basically payloads.
Your test needs to create the payload and fire it off at the correct location. I suggest creating and using
payload classes to make tests more readable.

eg.

        $loginPayload = new LoginPayload();
        $loginPayload->setUsername("fred");
        $loginPayload->setPassword("fredHasAPassword");
        $i->sendCurlRequest($loginPayload, false, "Some message about the action we just took");
