# Acceptance tests

All Codeception acceptance tests go in this folder.


## Structure

    ../_data/ # not sure yet
    ../_output/ # logs and screenshots of runs
    ../_support/ # custom steps for scenarios?
    ../bin/
        phantomjs/ # created by phantomjs-server.sh
        phantomjs-server.sh
    *Cest.php # individual test scenarios
    

## Running tests

These tests are currently configured to run within Schoolbox's Vagrant box.
The acceptance tests are configured to automatically start chromedriver
(the web driver for Chrome Headless).

If the tests do not run properly due to compatibility of chrome driver with 
your version of google chrome try updating the chromedriver with the one that
vagrant downloads in its vendor folder.

    cd /vagrant
    sudo cp -f /vagrant/vendor/bin/chromedriver /usr/bin/chromedriver

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

See `test/codeception/acceptance/LoginCest.php` for an example - it shows an example of
driving a web browser to perform commands, and of a direct database query.


### Guidelines

#### Document intent, not just actions

Codeception tests, in their simplest form, are a list of actions that take
place in a browser:

```php
$i->amOnPage('/news/);
$i->click('#content .icon-modify');
$i->fillField('heading', 'My Headline');
$i->click('input[type="submit"]');
$i->seeInField('heading', 'My Headline');
```

However, simply defining a test as a list of actions has issues:
* it doesn't really document what the end user is actually doing, from their
  perspective
* its intent isn't clear from reading; what is the user actually modifying?
* it's fragile; what happens if the page's design changes?

> If a selector for an element that a test needs to find is complicated, this
  is a sign that either the page's layout needs to be simplified, or the
  selector needs to be simplified (by adding an ID, perhaps?)

A possible solution to these issues is to create
[page objects](https://codeception.com/docs/06-ReusingTestCode#PageObjects)
for each type of page for which a test is being written.

I've found that these basics elements have been useful for page objects:
* it should have a `generateRoute` method, which can generate a URL to a page
  * this can be used by `$i->amOnPage()` to easily visit a URL represented by
    the page object
* if it's a form, it should have `fill`/`see` methods for each input
  * this can be used in the test to set input values, without it having to know
    exactly what type of field it is
  * ideally, the base test will not have to interact with form inputs at all

This means that this can be rewritten as:

```php
// in test
$i->amOnPage($newsIndexPage->generateUrl());
$newsFormPage->modifyNewsItem(1);
$newsFormPage->fillHeadline('My Headline');
$newsFormPage->submit();
$newsFormPage->seeInHeadline('My Headline');

// in newsIndexPage object
public function generateUrl() { ... }

// in newsFormPage object
public function modifyNewsItem($position) { ... }
public function fillHeadline($headline) { ... }
public function seeInHeadline($headline) { ... }
public function submit() { ... }
```
