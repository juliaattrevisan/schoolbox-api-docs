# Smoke Tests

All Codeception integration tests go in this folder. Smoke tests also live here as they do not need the
 webdriver used in acceptance tests.

## Structure

Same as the structure in acceptance tests, you can see it here: 
[Acceptance Tests](./test/codeception/acceptance/README.md).

## Running tests

Currently smoke tests use the Integration set of helpers and modules, needs to be revisited in the future.

### Configuration

Same as acceptance tests as they are all based on the same framework. 
Found in documentation here: [Acceptance Tests](./test/codeception/acceptance/README.md).

### Codeception test

To run smoke tests, like acceptance tests, you call codeception, 
select the suite to be smoketests and then select the test you are after.

So to run all the smoke tests:

    vagrant ssh
    cd /vagrant
    ./vendor/bin/codecept run --steps smoketests

To run a specific test:

    ./vendor/bin/codecept run --steps smoketests LoginCest.php


## Writing tests

See `test/codeception/smoketests/LoginCest.php` for an example - it shows an example of a simple creation
of a payload and then firing it off at the schoolbox endpoint.

### Guidelines

#### Document intent, not just actions

A little different from the Acceptance and Integration testing as the actions are basically payloads.
Your test needs to create the payload and fire it off at the correct location. I suggest creating and using
payload classes to make tests more readable.

eg.
         
    #WHEN
    $loginPayload = new LoginPayload();
    $loginPayload->setUsername("fred");
    $loginPayload->setPassword("fredHasAPassword");
    $i->sendRequest($loginPayload);

    #THEN
    $i->seeResponseCodeIs(303);
    $i->sendGET($i->grabHttpHeader('location'));
    $i->seeResponseCodeIs(200);

Note that the code checks the response code then performs the redirect and gets the final response code.