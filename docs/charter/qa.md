# Quality Assurance (QA)
* [The Role of QA?](#the-role-of-qa)
* [Areas to QA](#areas-to-qa)
* [QA Checklist](#qa-checklist)
* [Tools that help](#tools-that-help)

## The Role of QA
Quality assurance covers:
* Functionality
* Useability

There are many facets in these two sections but essentially you are making sure the system is 
functional and useable. 

This is done on each point release through the QA of pull requests on code created by the previous 
two steps in the developement process.
Here is the process step mentioned in the [definition of done](./definition-of-done.md).

## Areas to QA

Every PR has multiple areas to QA I group them into testing types or focuses.

Test Types:
* Functional
* Acceptance
* Regressions

**Functional**

Does the Pull Request code meet all the Acceptance criteria?

This should be really covered by the review but it is a step that I still do, but can be skipped if our 
review process is rebust enough.

Does the Code handle strange situations and paths?

This is the "If a user does x does it crash" tests. Using foreign character sets, null values where there
should be correct data. Basically try and break the code that has been changed.

**Acceptance**

Was the section that was changed intuitive?

This is a very subjective meassure but you have to channel your inner incompetent user. 
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

## QA checklist

* Acceptance Criteria Met?
* Is the feature easy to use?
    * Did you have to respeat steps because its unintuitive?
    * Was the feature slow to load? 
    * Hard to understand? 
    * Instructions for user on how to use wrong?
* Does the change effect the front end (styling, js ect.)?
    * If so does it work on google chrome, firefox, IE 11 and mobile viewport?
    * We also support safari, but I generally stick to the three above.
* Strange user behavior doesn't break the system?
    * Different language characters.
    * Null values, incorrect input.
    * Incorrect workflow. eg. Setting a mark as one type in assessment, 
    submitting a draf, then changing the mark type.
* Impact of Change feild have any other areas effected? Do they work?
    * If Impact of Change is empty, does the PR owner know of the impact of their code?
    * If not then does the code show the impact of change?
    * Can the user see things they shouldn't see due to the change?
    * Can the user do things they shouldn't be able to do due to the change?

## Tools that help

There are quiet a few tests available that can be done as part of the manual QA:
  * [Acceptance Tests](../../test/codeception/acceptance/README.md)
  * [Smoke Tests](../../test/codeception/integration/README.md)
  * [Behaviour Tests](../../test/behavior/README.md)
  * [Unit Tests](../../docs/unit-test.md)
  * Notification Tests

**Acceptance Tests**

These will become eventually the regression tests. The coverage will depend on critical bugs and generally 
will be created as a reaction to these bugs to attempt to prevent them from re occuring in the future

**Smoke Tests**

Will be part of CI process on each pull request. Only tests for fatal failures. Useful for checking on 
major regressions.

**Behavior Tests**

Dated tests. Are not being maintained at the moment.

**Unit Tests**

Are part of CI, happen each pull request. They only test functionality.

**Notification Tests**

Not maintained or documented. Can revive the tests as they are right now broken. Run by running the harness:

    php ./test/notif/harness.php


## Maintenance of Automation
  
Automation for the process is progressing. Some of the tooling is in need of maintenance. Jira tickets to 
clean up, create or fix tests in this area are welcome. To do, in progress and review steps need to take 
priority before these fixes and changes to not jeopardise sprint completion.

So if you are looking to fix a test or to create one:
* Create a Jira ticket or identify which Jira ticket the PR will be attached to.
* Create a branch based on the Jira ticket like you do with any bug or feature.
* Fix the test and ensure its working. Rebase if nessasary.
* Create a PR for the test fix.

Its best to create a new Jira ticket when the Jira ticket that breaks the change is already closed/done.
This is because the test fix can go into the current sprint for visibility. Review happens in the same way 
as with any other ticket. Big focus is on maintainability.