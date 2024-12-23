# Quality Assurance (QA)
* [The Role of QA](#the-role-of-qa)
* [Note for Custom Changes](#note-for-custom-changes)
* [Areas to QA](#areas-to-qa)
* [QA Check list](#qa-check-list)
* [QA Data Migration](#qa-for-data-migration)
* [QA transition to done response](#qa-transition-to-done-response)
* [Tools that help](#tools-that-help)

## The Role of QA

The Role of QA is to reduce the amount of bugs introduced and currently present in the system. It is the official testing step of the process but catching defects and bugs should be done on every step. QA testing focuses on the code that was changed and its:
* Functionality
* Usability
* Changes Impact

**Functionality**

When you use the code it runs and does what it is meant to do. You can achieve the tasks that are solved by the change. And other parts of the system that the introduced code can effect do not break. The key thing here is that no other functional parts of the system break by the addition or change introduced by the bit of code being tested.

**Usability**

The introduced code does not make the work flow confusing or over complicated. When using the system the change doesn't require the user to learn how to use the system. It just works the way they intuitively think it will work based on the easy to read and clear information that we present to them. The system correctly fits the needs of its target audience and the change helps to make the system better for the target audience when they use it. Subject matter experts can be consulted if you are worried about a particular work flow.

Note, not all of the criteria in usability can always be met, some problems we try to solve are complicated by nature.

Note 2, PO can over ride our ideas of usability.

**Changes Impact**

QA is required to be done on each change in the code before it makes it to production. It is done directly after the code change has been merged into development codebase. It should be done with testing scope in mind, meaning that we should focus on what areas of the system are actually effected by the change. This should be facilitated and provided by the Change Impact field in the ticket. This may require consultation with other devs.
There is an explanation of the QA step as it relates to the definition of done [here](./definition-of-done.md).


## Note for Custom Changes

Schoolbox staff are generally unable to verify that a custom work indeed
functions to the expectations of the client, as generally only the client is
certain of the contents of their data set.

QA of custom changes should be a joint effort between:
- the code's author
- Schoolbox's project manager or client liaison for the custom job
- a representative of the client

The three representatives will communicate between each other to determine any
changes which need to be made before the work passes QA, whilst minding the time
and budget constraints of both the client and Schoolbox.


## Areas to QA

Every PR has multiple areas to QA I group them into testing types or focuses.

Test Types:
* Functional
* Acceptance
* Regressions

**Functional**

Does the code that was changed meet all the Acceptance criteria?

This should be really covered by the review but it is a step that I still do, but can be skipped if our
review process is robust enough.

Does the Code handle strange situations and paths?

This is the "If a user does x does it crash" tests. Using foreign character sets, null values where there
should be correct data. Basically try and break the code that has been changed.

**Acceptance**

Was the section that was changed intuitive?

This is a very subjective measure but you have to channel your inner incompetent user.
What does the user expect to see? Do we present something that they can understand? If they use standard
behaviour to interact with the change does it react in an expected way?

Was the section easy to use?

How many clicks and steps does it take to do the action and is this needlessly complicated. Is the display
clunky and hard to read? Is there too much information? How slow did the section load?

Does this work on our main browsers we support?

If the change is a front end change you have to test the main browsers we support and in a mobile view.
It is good to test on Firefox, Google Chrome, IE 11 (through a vm) and mobile view at a minimum.

**Regressions**

Has the change in this code effected sections outside of the area the change was looking to fix or create?

This has to be the hardest area of testing to cover. You need to either read the code, ask the dev who wrote
the code or look at the "impact of change" section in the ticket.

Anything compromised by the change?

Has any data been compromised by the change in the code, can a user see something they shouldn't be able to
 see? Or can they gain access to something they shouldn't be able to do?

## QA check list

* Acceptance Criteria Met?
* Is the feature easy to use?
    * Did you have to repeat steps because its unintuitive?
    * Was the feature slow to load?
    * Hard to understand?
    * Instructions for user on how to use wrong?
* Does the change effect the front end (styling, js ect.)?
    * If so does it work on Google Chrome, Firefox, IE 11 and mobile view-port?
    * We also support safari, but I generally stick to the three above.
* Strange user behaviour doesn't break the system?
    * Different language characters.
    * Null values, incorrect input.
    * Incorrect work flow. eg. Setting a mark as one type in assessment,
    submitting a draft, then changing the mark type.
* Impact of Change field have any other areas been effected? Do they work?
    * If Impact of Change is empty, does the PR owner know of the impact of their code?
    * If not then does the code show the impact of change?
    * Can the user see things they shouldn't see due to the change?
    * Can the user do things they shouldn't be able to do due to the change?

## QA for Data Migration

This particular process is focused on ensuring data integrity when we have changes being done to the structure of the database, this includes:

* any ALTER TABLE update
* any update which changes the table or field in which a particular piece of information is found
    * i.e. a migration of existing data to a new table or field: usually follows an ALTER TABLE
* any other update whose result is not reversible
    * i.e. a mass hard-delete, or mass update whose original value can't be restored
* if in doubt consider the change a migration (safe is better than)

It is best practice to keep the migration as simple as possible. Each change being tested with this process before proceeding to the next. This is to reduce complexity in the last step of observation of the results.

The process itself can be divided in three steps:

* Set-up
* Test
* Observe

This test process should be done for a migration from master to the latest develop which will execute the migration scripts.
In the event we need to test further into the past because we need to update data on master (strongly recommend against this) there must be a valid past version for QA before the change on master is merged.

**Set-up**

1. git checkout master.
1. provision on qa-test dataset.
1. review that there is data ready to test migration with.
    1. if there is no data build said data
    1. after building any additional test data save the qa-test dataset.
1. identify what you expect each case to turn into on the other end.

**Test**

1. you should be on master branch with data ready from set up step
1. git checkout develop
1. vagrant ssh (needs to be run on vagrant)
1. cd /usr/share/schoolbox
1. run sudo tools/build-sql-update.sh
1. run sudo tools/update-sql.sh
1. run npm install
1. run npm run build

**Observe**

1. you should be on develop branch in vagrant with test completed. Ready to check status after migration.
1. checks you will do are a combination of on vagrant (scripts), db (the data itself) and the app ui using a browser.
1. run through [QA check list](#qa-check-list).
1. ensure changed data doesn't regress functionality and usability. (Mainly though app on a browser)
1. check the actual changed data to see if it adheres to what we expect. (Database check)
1. based on findings during observation pass or fail the migration test.


## QA transition to done response

Its best to communicate what has been tested during QA. More detail is better than less in that regard. This is mainly so that other developers who notice their ticket going into the Done state can pull it back into QA if they believe a combination is missing from what has been tested.

With that in mind currently it would be good if we saw testing reported to be shown in the following format:

**QA Pass and failure response format**

Page or Area of system that was QA'ed
* Functionality or action validated in testing.
     * Variations of that action tested.

**Example**

Admin settings, weather API config.
* Can set a valid key.
     * Weather Component can be seen on staff, student and admin dash.
* Can set an invalid key.
     * Weather Component is seen as unavailable for staff, student and admin users on dash.

Each test case should state if its passed or failed. Grouping similar test cases if fine as long as its clear what has failed if only 1 out of the group has failed.

When noting a failure it should happen after the main tested section and should remark STR so that the dev can replicate the bug quickly. Output of stack trace if there is one is helpful too.

**Example of failure notes**

Tested section

Failure 1. Can input all character types and save them on a news articles.

STR

* On vagrant, sales data set
* Login as alaress
* Go to: https://vagrant.dev.schoolbox.cloud/news
* Create a news article.
* In body place in some text in Russian using Google translate.
* Save the news article.

Expected: Works fine

Actual: Stack trace

Note it is also fine to place what failed directly under the test that failed. As long as its obvious how to replicate the issue.

## Tools that help

There are quite a few tests available that can be done as part of the manual QA:
  * [Acceptance Tests](../../test/playwright/README.md)
  * [Smoke Tests](../../test/codeception/integration/README.md)
  * [Behaviour Tests](../../test/behavior/README.md)
  * [Unit Tests](../../docs/unit-test.md)
  * Notification Tests

**Acceptance Tests**

These will become eventually the regression tests. The coverage will depend on critical bugs and generally
will be created as a reaction to these bugs to attempt to prevent them from re occurring in the future

**Smoke Tests**

Will be part of CI process on each pull request. Only tests for fatal failures. Useful for checking on
major regressions.

**Behaviour Tests**

Outdated tests. Are not being maintained at the moment.

**Unit Tests**

Are part of CI, happen each pull request. They only test functionality.

**Notification Tests**

Not maintained or documented. Can revive the tests as they are right now broken. Run by running the harness:

    php ./test/notif/harness.php


## Maintenance of Automation

Automation for the process is progressing. Some of the tooling is in need of maintenance. Jira tickets to
clean up, create or fix tests in this area are welcome. TO DO, IN PROGRESS and REVIEW steps need to take
priority before these fixes and changes to not jeopardise sprint completion.

So if you are looking to fix a test or to create one:
* Create a Jira ticket or identify which Jira ticket the PR will be attached to.
* Create a branch based on the Jira ticket like you do with any bug or feature.
* Fix the test and ensure its working. Rebase if necessary.
* Create a PR for the test fix.

Its best to create a new Jira ticket when the Jira ticket that breaks the change is already closed/done.
This is because the test fix can go into the current sprint for visibility. Review happens in the same way
as with any other ticket. Big focus is on maintainability.
