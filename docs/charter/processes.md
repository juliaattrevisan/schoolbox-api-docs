# Ticket Processes

## Bug
* Bug is logged, ideally with all details for our Definition of Ready complete (steps to reproduce, acceptance criteria, etc).

* Someone from the Product Team reviews the ticket, confirming the bug exists in Schoolbox, fills in any missing details to get the ticket to our Definition of Ready.

  * It's important that this person applies a critical mindset during this process (to an extent), not taking everything the reporter has written as gospel.

* Bug is inserted into the Backlog, with a priority set, for someone to pick up.

* Bug is picked up by anyone and a fix is coded.
  * "anyone" generally refers to a member of the Product Team.
    People outside of the Product Team may submit fixes, but must accept that
    the Product Team:
    * is under no obligation to merge or even review the fix
    * takes ownership of the bug once it has entered sprint
      (which it should do before it is reviewed)

* The required pull requests are submitted on GitHub, and the ticket is moved to 'Awaiting Review' in Jira.
  * Usually, two pull requests are required: one based on master, and the other on develop.
    * A master PR is not required if the fix:
      * resolves an issue that occurs only in develop
      * includes code changes deemed an unacceptable risk of regression
        (by consensus of the Product Team)
      * introduces a change which necessitates a change management effort by the
        Professional Services team (i.e., it changes a process or user interface)

* A different developer reviews the PRs, static reviewing the code, and functional testing the area. In most cases there will be changes required, in which case the reviewer leaves comments on GitHub, and pushes the ticket back to 'In Progress' in Jira.

  * If the reviewer finds issues with the PR (most cases) - that person will leave comments on the PR, and push the ticket back to 'In Progress' in Jira, for the original developer to fix up and put back into 'Awaiting Review'.

  * If/once the reviewer is happy with the PRs - the reviewer will merge the PRs into the code base, pushing the ticket into 'Awaiting QA' and setting the fix version (next point release for master and develop fixes, or the next major release for develop only fixes).

* Another member of the Product Team picks up the ticket for QA, focusing on functional testing the area that has been changed.

  * If that person finds any issues that have been caused by the fixes for this ticket - they will 'QA Fail' the ticket, sending it back to Ready and back through the coding and review process.

  * If the person is happy with everything, they will 'QA Pass' the ticket and it will go into Ready, ready to deployed with the release.

### Important Notes and Caveats
* For the most part you should not be merging any code you have written that no one else has seen and approved.
* Minimum of 2 (ideally 3 different people) have seen the Ready state of a ticket before it starts being coded.
* Every code change should have 3 different people approve it over the course of coding, reviewing and QA'ing.
* In the case that the QA person finds a small problem, they are welcome to review the fix, and continue QA'ing.

## Story & Task
* Story or Task is created by anyone, it goes straight to 'Ready' in Jira.

* The ticket is not worked on until either:

    1. The ticket is labelled `expresslane` by the SM or PO
    2. The ticket goes through at least a Sprint Planning meeting, ideally a Refinement meeting prior to that.

* The coding process follows the same as above, except there is just one PR for develop (unless custom work or otherwise stated).

## Epic
* Represents a problem space
* Has stories which provide the solution to the problem
* Has activities which represent research that needs to be undertaken before stories can be generated

### Workflow:
* New: The issue is new - devise activities (confirm the problem statement).
* In Discovery: Discovery activities, such as research, user interview, usability tests, are underway.
* In Definition: Definition activities, such as affinity mapping & empathy mapping, are underway.
* In Brainstorm: Brainstorming is underway. Includes activities such as mock-ups, prototyping, etc.
* Under Development: Exactly what it sounds like.
* MVP Delivered: Minimum Viable Product has been delivered. Resolution should be MVP.
* Closed: Resolution should be Done.

# Development processes
## Review
- Statically review code for logical correctness, efficiency, adherence to best practices and conventions, readability, etc.
- Consult with the author of the code, if necessary, to gain understanding of the changes made.
- Functionally test all parts of the system that have been changed, and ensure they meet the Acceptance Criteria defined on the ticket.
- Move the ticket back to "In Progress" if any problems are found, and leave comments on the PR outlining what needs to be changed.

## QA
- Check the `files` tab on the PR to see what parts of the system were changed.
- Functionally test these parts of the system to ensure:
    1. That they fulfill the Acceptance Criteria defined on the ticket
    2. That all features available in these parts of the system otherwise behave the same as before.
