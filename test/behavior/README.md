# Schoolbox Behavioral Testing

This README outlines how Schoolbox's behavioral tests work, and how to set them up.

## Requirements

* Behat (will be installed by Composer)
* Behat Selenium2 driver (will be installed by Composer)
* Selenium Server (available from http://docs.seleniumhq.org/download/)

Currently, Behat is configured to run tests against Firefox only. It should be
simple to configure it to run against Chrome or Internet Explorer.

The Selenium driver is also capable of driving the PhantomJS headless browser
(available from http://phantomjs.org/download.html). This may be the best option
for CI testing, as it's able to be run from a command line without a window
system, and supports JavaScript fully unlike other headless browser
implementations.

## Running behavioural tests

If using the Selenium Server, start it with the command: `java -jar /path/to/selenium-server-standalone-x.y.z.jar`.
It's currently set up to run Firefox only.

If using the PhantomJS headless browser, start it with the command: `/path/to/phantomjs-dir/bin/phantomjs --webdriver=4444`

