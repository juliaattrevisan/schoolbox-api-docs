# Acceptance tests

All Codeception acceptance tests go in this folder.


## Structure

    _data/ # not sure yet
    _output/ # logs and screenshots of runs
    _support/ # custom steps for scenarios?
    bin/
        phantomjs/ # created by phantomjs-server.sh
        phantomjs-server.sh
    *Cest.php # individual test scenarios
    

## Running tests

These tests are currently configured to run within Schoolbox's Vagrant box.
The acceptance tests are configured to automatically start chromedriver
(the web driver for Chrome Headless).


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


### Codeception test

To run the Codeception tests:

    vagrant ssh
    cd /vagrant
    ./vendor/bin/codecept run --steps

To run a single test, provide the suite (`acceptance`) and the test file name,
relative to `test/acceptance`:

    ./vendor/bin/codecept run --steps acceptance LoginCest.php



## Writing tests

See `test/acceptance/LoginCest.php` for an example - it shows an example of
driving a web browser to perform commands, and of a direct database query.


### Guidelines

Document intent, not just actions

Codeception tests, in their simplest form, are a list of actions that take place in a browser.

This isn't overly helpful to someone reading the test, as from a user's point of view
an interaction isn't just them filling in a form field, or clicking on something:
it's part of a larger whole which involves them fulfilling some sort of business need.

In addition: the user isn't interacting with a field with a particular ID, name
or CSS selector, so don't show these in Cests; abstract them in page objects.


My current recommendation is:

Create [page objects] for each page class you're testing
* it should have a `generateRoute` method, with a negotiable param count
  * used by `$i->amOnPage()`
* if it's a form, create fill/see methods for each input


Sophisticated but generic interactions (example: selectize, file uploads) 
should be written into acceptance tester.
