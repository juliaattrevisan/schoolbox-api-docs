# Definition of Ready

No coding should start on a ticket until the ticket has met the Definition of Ready.

> TODO consider an automated mechanism for verifying that a ticket is "ready"?

Tickets that meet the definitions below may have its status moved from 'Unconfirmed' to 'Ready' in Jira, signalling that the ticket has met the Definition of Ready.

Bugs that are 'ready' may be brought into Sprint at any time as part of BAU. Stories and Tasks must either go through Sprint Planning, or be agreed upon as a team before going into Sprint. The one exception to this is if the task or story has an 'expresslane' label, in which case the ticket can be brought into Sprint at any time like BAU.

## Epic
* Problem statement is generated and documented.

## Bug
* Steps to Reproduce field populated:
    * Local/Vagrant steps to reproduce if attainable.
    * Steps to reproduce on a dev or live school server added if relevant or in lieu of local steps.
    * If neither of the above are possible, a brief message explaining so included.
* Acceptance Criteria
* Priority is moved from Low if applicable
* Ticket is reviewed by another team member.

## Story, Task
* Acceptance Criteria
* Detail includes assumptions, constraints, business rules, technical, performance, security.
* External dependencies ideally resolved, but at minimum identified.
* Agreed to enter Sprint by the Product Team, or the 'expresslane' label added by PO or SM.
* Story isn’t larger than can be realistically be completed in 5 working days
    * If it is, decomposition is required.

## Custom Work
* Budget, cost centre (NetSuite Job ID for logging time)
* Contact information for customer
