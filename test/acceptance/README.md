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

### Reprovision

If you've not yet reprovisioned after checking out this branch: run
`vagrant provision` to install Chrome, Chromium WebDriver and its dependencies.

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
