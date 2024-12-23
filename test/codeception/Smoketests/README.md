# Smoke Tests

All Codeception smoke tests go in this folder. Integration tests also live here.

## Structure

    ../_data/ # not sure yet
    ../_output/ # logs and screenshots of runs
    ../_support/ # custom steps for scenarios?
    ../bin/
        phantomjs/ # created by phantomjs-server.sh
        phantomjs-server.sh
    *Cest.php # individual test scenarios

## Running tests

Currently smoke tests use the Integration set of helpers and modules, needs to be revisited in the future.

### Configuration

Base configuration is stored in `codeception.dist.yml` within the project's
root folder.

You may provide your own overrides by adding them to `codeception.yml`. This
file is in `.gitignore`, so should not be committed to the base repository.

> An example override is if you do not want to delete records of successful
  test runs:
>
>     extensions:
>         enabled:
>             - Codeception\Extension\Recorder:
>                 delete_successful: false

### What is covered by the smoke tests

### Codeception test

To run smoke tests, like acceptance tests, you call codeception,
select the suite to be smoketests and then select the test you are after.

The tests require you to have provisioned the `sales` dataset.

So to run all the smoke tests:

    vagrant ssh
    cd /usr/share/schoolbox
    ./vendor/bin/codecept run --steps Smoketests

To run a specific test:

    ./vendor/bin/codecept run --steps Smoketests LoginCest.php


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
