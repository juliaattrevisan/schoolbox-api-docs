# Schoolbox\Database\Test

This folder contains "unit tests" that require database access.
Notice that the internal folder hierarchy correlates to the classes being tested.

These tests are collated in this special directory so they can be centrally
excluded from the proper unit tests run by CI.

To run these tests, invoke the `databaseTest` suite in /phpunit.xml

**FAIR WARNING**

Running these tests will obliterate anything in your database.
Ensure you've backed up anything you care to lose.