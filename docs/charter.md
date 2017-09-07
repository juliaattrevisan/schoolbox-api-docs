# Development Team Charter

The co-authored and mutually-agreed-upon charter of practise of the Development Team of Alaress.

## Meta

### Tools

* PHPStorm, same code standard, inspections
* Jira (full detail of function), story, agile sprint board
* Github, PR and code reviews
* Zendesk, to communicate with customer
* Build CI
* HipChat
* Developer Channel (developers only, standup bot)
* Developer Support Channel (interface to company)
* Knowledge base (no tool yet) 
* New Relic (reports issues, stack traces)
* Gmail &rarr; minimise email usage, team alerts,memos

### Responsibilities

* Align and understand with company vision and mission
* Maintain our definition of done
* Commit to deliver what (and by when) we agree
* Attend all ceremonies, on time and with any necessary preparations.
* Ensure sprint runs smoothly by grooming backlog ensuring appropriate preparations have been conducted before work development commences
* Interacting with customers to assist with diagnosis and delivery
* Conduct discovery and definition with a PM or PO

### Hats

Hats can be swapped as required.  If a person is unavailable another person must be assigned the hat for that day/sprint. The onus for hat reassignment lies with the current wearer of the hat.

#### Product Owner

Communicate priority and customer requirements and business requirements, determine value

* Prioritise the backlog
* Available to the customer and management
* Available to team for all ceremonies
* Release planning and coordination

#### Scrum Master

Will serve to facilitate and enable team to deliver

* Ensure sprints run smoothly and are communicated
* Minimise roadblocks
* Manage interruptions and facilitate changes to sprint
* Be forward-looking to the priority of the backlog and maintain oversight over the foundation and elaboration work that needs to be conducted, ensuring that is gets done in time.

#### Master of Ceremonies

* Organise and facilitate all ceremonies ensuring they run effectively.
* During ceremonies, outranks all real-world hierarchy to keep meetings on-task.

#### Project Manager

* Stakeholder management: Communicate and coordinate customer/company interaction
* Risk management
* Manage/coordinate research, discovery and foundation work 
* Inform/influence release planning, backlog prioritisation and sprint planning
* Maintain metrics (quality, cost, and execution/capacity)
* Contract Management: Ensure billing, contract requirements are met and maintained
* Facilitate interactions with other teams - coaching and process improvement
* Maintain project documentation 

#### UX

##### Problem Elicitation 

Engage with stakeholders to uncover the underlying problems that are trying to be addressed. 

##### Discovery

Gather data by performing research tasks, such as analytics, workshops, interviews, focus groups, surveys, user tests (eg. True Intent, Remote Unmoderated, Local Moderated, etc.)

##### Definition 

Coalesce data from Discovery into a usable state, such as personas, empathy maps, user journeys, affinity maps, etc. The **most important** activity in this phase is generation of a User Story Backbone that can be used as a starting point for story decomposition.

##### Foundational work 

“Just Enough, Just in Time.” This work needs to be started several sprints in advance, as orchestrated by SM. While this does include cross-cutting concerns and external influences, from specifically a UX Design standpoint this represents \[ sketches &rarr; flows &rarr; mockups &rarr; live prototypes ] so that—by the time the story lands in the sprint—the fundamental UX considerations have been mostly dealt with.

#### Developer

* Complete all assigned stories and tasks in your sprint pipeline
* Actively assist fellow developers with their sprint pipeline if you finish yours early
* Fall back to BAU or foundation work (as directed by the Scrum Master) if no further sprint assistance can be rendered

#### Spiker

* Perform technical investigation on a ticket prior to it reaching Sprint. See [the spike guide](spike.md) on how to do that.

#### Technical Lead

* Provide support to other developers in the form of guidance, mentoring and up-skilling.

#### Peer Reviewer

* Static analysis
* May also include functional testing

#### User Acceptance Tester

* Client representative

#### Functional Tester

(This will eventually become automated)

* Identifying potential flaws
* Testing beyond limits
* Chaos testing

#### Business as Usual (BAU) Triage Nurse 

* Move issues from Unconfirmed to Confirmed
* Fix critical issues
* Add items into sprint and backlog if necessary

### Ceremonies

* Showcase **Last day of sprint 3:00pm** 
* Retrospective **First day of the sprint 10:00am** 
* Sprint planning **First day of the sprint 11:00am**
* Standup **11:50am daily** 
    * Be transparent about status (green, yellow, red)
    * This is not the place for in-depth discussion or simply "this is what I'm working on" (we have the chat-bot for
      that); if you're blocked by something, make it known and flag the appropriate people for followup immediately
      after the standup.
* Backlog grooming **2:00pm Tuesday, Thursday**
    * Distribution of foundational work (including tech spikes, elaboration, AC refinement) is an outcome of this ceremony
* Custom work review **Monday 4:00pm**


### Team Cohesion

#### Accountability to your team
* Proactively help and support each other as required
* Make sure you meet expectations and commitments to your team
* Share the responsibility as a team, no individual should be wholly responsible 

#### Show respect at all times and appreciate others input and work
* Treat others as you wish to be treated
* Don’t talk over other people
* Respect the shared workspace, noise, cleanyness, your actions impact others
* Consider everyone is an individual with their own personal background and perspective

#### Ensure that you are approachable and open
* Be honest and transparent about your perspective
* Actively listen while others are speaking

#### Ensure feedback is constructive and aligned with shared goals
* Failure is expected, we learn from it
* Personal opinion should still be respected and not critiqued 

##### Feedback format
* Obtain permission
* Positive intent
* Specific impact
* Provide opportunity to respond

#### Rules for communication
* Communicate in method appropriate for the urgency (use online chat, email, github over personal interruption)
* Ensure you respond to pending communication in a timely fashion 
* Do not stay in DND for long periods of time
* Maintain your status in HipChat
* Attend standup and actively engage

#### Value
* Ensure everyone knows how what they are working on is valuable, and fits with the company vision and mission

### Obligations to Business
* Support the product internally
* Log time for custom work
* Ensure efficient delivery, minimise cost and wastage

## Guidelines for Ready

> The Code is more what you'd call 'guidelines' than actual rules.

~ Barbosa

This is considered a set of ideals, acknowledging that it isn't always practical or possible; that isn't an excuse to be lazy though!

### Story, Task or Bug

* Acceptance Criteria
* Steps to Reproduce on (bugs) or acceptance criteria (stories)
    * Potentially, STR or AC won’t exist before ready, but they **are** required before ticket can be marked as Done
* Moved from Unconfirmed to Ready (bugs)
* Detail include assumptions, constraints, business rules, technical, performance, security
* External dependencies ideally resolved, but at minimum identified
* Story Points
* Story isn’t larger than can be realistically be completed in 5 working days
    * If it is, decomposition is required

### Custom Work

* Budget, cost centre (FP Job)
* Contact information for customer

## Definition of Done

In contrast to the Guidlines of Ready above, these are NON-negotiable.

### Story, Task or Bug

* JIRA ticket contains;
    * Acceptance Criteria
    * Steps to Reproduce (if bug), or acceptance criteria
    * Detail include assumptions, constraints, business rules
    * Story Points (if not bug)
* Documentation
    * README for module is created/updated
* Code is written to standards
    * Unit tests
    * Genesis tests
    * Selenium tests
* Developer confirms code is ready for review
* Peer Review
    * Code Standard 
    * Static analysis
    * Unit test run
    * Code runs and is functional in accordance to acceptance criteria
* Quality Assurance
    * Ensure that system working to an acceptable level
    * Check the acceptance criteria are still met

### Sprint
* Committed stories done
* Showcase
* Retrospective

### Minor Release
* Release notes are written
* Regression Testing
* Based on release notes
* Utilise and chaos test all function affected by release notes
* Build is packaged and passes all checks

### Beta Release
* Release notes are written
* Testing ????
* Build is packaged and passes all checks
* Non Functional Requirements (installation, system, etc) have been documented.
* Important Change Management tips have been documented and reviewed.

### Major Release
* Beta Release
* UAT Testing
* Everything in minor release


