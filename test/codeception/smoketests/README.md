# Integration and Smoke Tests

All Codeception integration tests go in this folder. Smoke tests also live here as they do not need the
 webdriver used in acceptance tests.

## Structure

Same as the structure in acceptance tests, you can see it here: 
[Acceptance Tests](./test/codeception/acceptance/README.md).

Smoke tests currently live in SmokeTests/

## Running tests

All tests run with a PHP browser, CLI module and DB connection modules. 
The PHPbrowser is basically a webdriver without the front end. So it cannot test java.

The smoke tests do not even hit the PHP browser interface they instead use the curl client underneath. 
This is to increase the speed of tests.

### Configuration

Same as acceptance tests as they are all based on the same framework. 
Found in documentation here: [Acceptance Tests](./test/codeception/acceptance/README.md).

### Codeception test

To run integration tests, like acceptance tests, you call codeception, 
select the suite to be integration and then select the test you are after.

So to run all the integration tests:

    vagrant ssh
    cd /vagrant
    ./vendor/bin/codecept run --steps integration

To run a specific test:

    ./vendor/bin/codecept run --steps integration LoginCest.php

To run all the smoke tests you would run an integration test and point the tester to run all the tests inside the smoke test folder.

    ./vendor/bin/codecept run --steps integration SmokeTests

## Writing tests

#### Integration Tests

None as of yet.

#### Smoke Tests

See `test/codeception/integration/LoginCest.php` for an example - it shows an example of a simple creation
of a payload and then firing it off at the schoolbox endpoint.

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
        
