# Review
* [The Role of Review](#the-role-of-review)
* [Process](#process)
    * [Core Changes](#core-changes)
    * [Custom Changes](#custom-changes)


## The Role of Review

The main role of the code review process is to verify that the code has been
written to a particular standard.

The role of review is not so much to verify that the code as written meets the
Acceptance Criteria (that is the role of QA), but may be done anyway.


## Process

The processes for review (and QA) are slightly different for core and for custom
changes.

Review of custom changes done to slightly lower standards than for core, due to:
* Time constraints of Schoolbox staff
* Time or budget constraints of the client
* Schoolbox staff generally being unable to verify that a custom work displays
  data known only to the client correctly

### Core Changes

> A core change is any change to Schoolbox which changes the functionality of
> Schoolbox for all instances.

Review of core changes requires the following process:

- Statically review code for non-functional attributes, including but not
  limited to:
  - logical correctness
  - efficiency
  - performance
  - adherence to best practices and conventions
  - readability
  - security
- Consult with the author of the code, if necessary, to gain understanding of the changes made.
- Functionally test all parts of the system that have been changed, and ensure they meet the Acceptance Criteria defined on the ticket.
- Move the ticket back to "In Progress" if any problems are found, and leave comments on the PR outlining what needs to be changed.


### Custom Changes

> A custom change is any change to Schoolbox which is confined to a single
> instance.
>
> Generally, custom changes will be commissioned and paid for by a single
> client.
>
> Intricate custom jobs may require changes to core parts of Schoolbox: these
> changes must meet the Core standards for review and QA.

Review of custom changes requires the following process:

- Verify that there is no change to core in the code: if there is, this must be
  reviewed and QA'd as a core change
- If this is indeed a custom change, only need to:
  - statically review code for non-functional attributes (as with core work)
