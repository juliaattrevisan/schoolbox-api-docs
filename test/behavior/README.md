# Schoolbox Behavioral Testing

This README outlines how Schoolbox's behavioral tests work, and how to set them up.

## Requirements

* Behat (will be installed by Composer)
* Behat Selenium2 driver (will be installed by Composer)
* Either:
    * Selenium Server (available from http://docs.seleniumhq.org/download/) and a recent Firefox
    * PhantomJS headless browser (available from http://phantomjs.org/download.html)

Currently, Behat is configured to run tests against Firefox only. It should be
simple to configure it to run against Chrome or Internet Explorer.

Selenium Server is likely to be better for building tests, as developers can get
visual feedback on what is happening. PhantomJS is a better option
for CI testing, as it's able to be run from a command line without a window
system, and supports JavaScript fully unlike other headless browser
implementations.

## Running behavioural tests

If using the Selenium Server, start it with the command:
`java -jar /path/to/selenium-server-standalone-x.y.z.jar`.

If using the PhantomJS headless browser, start it with the command:
`/path/to/phantomjs-dir/bin/phantomjs --webdriver=4444`
