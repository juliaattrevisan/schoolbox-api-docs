# Ceremonies

Ceremonies are the regular meetings we have. Along with the four formal events prescribed by Scrum, we also have
workshops that usually take the form of one of the defined UX sessions.

> Block quotes taken from the [Scrum Guide](http://www.scrumguides.org/scrum-guide.html#events).


## Sprint Planning
* **When:** First day of the Sprint at 11:00am
* **Where:** A boardroom
* **Timebox:** 60 minutes
* **Participants** Product Team

> Sprint Planning answers the following:
> * What can be delivered in the Increment resulting from the upcoming Sprint?
> * How will the work needed to deliver the Increment be achieved?

Prior to the Sprint Planning meeting - the Product Owner will prepare a rough Sprint worth of work.
This may be done on an ad-hoc basis in previous Sprints, or in a more formal meeting.

In this meeting we go through this work to get everyone up to speed on the work, assigning out specific tickets to team
members, and agreeing as a team on the unit of work that we plan to complete for the Sprint.

This meeting will result in:
* A Sprint Backlog populated with tickets, all of which meet the [Definition of Ready](definition-of-ready.md)


## Daily Standup / Scrum
* **When:** 10:30am every day of the Sprint (excluding the first and last days of the sprint)
* **Where:** Any convenient location. In practice, this ceremony is held at the Product Team's desk area, but may also
  be held in the Glass Conference Room.
* **Timebox:** 15 minutes
* **Participants** Product Team

> The Development Team plans work for the next 24 hours. The Development Team uses the Daily Scrum to inspect progress
> toward the Sprint Goal and to inspect how progress is trending toward completing the work in the Sprint Backlog.

The Scrum Master will give a quick summary of the current state of the Sprint and the Product Team (e.g. if there are
any absent team members).

From there, starting with the Scrum Master and moving clockwise from them, each team member will answer the following
questions:
* What have I been working on since the last Standup?
* What am I planning on working on until the next Standup?
* What and whose help will I need to finish my Sprint commitments?

When impediments are identified by team members - the team should collaborate to work out how to remove any blockers.

The Standup should be short and sweet, generally only high level details should be mentioned. In-depth discussion
required will be deferred until after the Standup.

Often the Product Owner or Scrum Master will utilise the Daily Standup to ask the team to focus their efforts in a
specific area where possible (e.g. QA'ing a point release).


## Refinements
* **When:** Monday, Tuesday and Wednesday of 2nd week of sprint at 11:00am, as required
* **Where:** A boardroom
* **Timebox:** 1 hour
* **Participants** Scrum Master, other Product Team members (two at most)

> Product Backlog refinement is the act of adding detail, estimates, and order to items in the Product Backlog.

During a refinement session, the Product Team endeavours to reach a shared understanding of the business requirements
and technical implications of the tickets being examined.

In practice, this results in the refinements being split into:
* Mon: ensure that Acceptance Criteria are in place for all tickets in this sprint and next
* Tue: ensure that Story Points are in place for all tickets in this sprint and next
* Wed: add AC and SP to any remaining tickets, if any were missed in the previous refinements (due to the timebox being
  exceeded)


## Showcase
* **When:** Last day of Sprint at 11:00am
* **Where:** The large boardroom
* **Timebox:** 1 hour
* **Participants** Product Team, representatives from other Teams

> Showcases are our chance to the whole company up to speed with where we're at with our development, and allow us to
> collaborate and get feedback on what we've got and where we're going.

Showcases will generally take the following form:
* The Product Owner will open the meeting with a quick summary what our focus was for the Sprint, what we got "done"
  and what we didn't get "done".
* The Product Team will showcase their work. Generally the people that did the work will be asked to present their work,
  but sometimes the Scrum Master will present other people's work.
* Feedback and questions will be asked by the audience at the end of each unit of work shown.
* The Product Owner and Product Team will briefly talk about the plan for the next Sprint, giving the audience a chance
  to give feedback and ask questions around that.

Work that may be presented:
* Research insights
* Sketches and wireframes
* Prototypes
* Production ready interfaces
* Changes to existing interfaces and functionality

What the Showcase shouldn't include:
* Bug fixes where there is no change to intended functionality - best left for the release notes.
* Minor changes or additions to user interfaces and functionality - it is too hard for the audience to get context with
  these, best left to release notes and Jira.
* Changes that have no functional impact.

As there will be non-technical people at the Showcase - we should always try to stay away from delving into technical
details. These discussions should be parked and happen after the Showcase.

> Note: the Showcase seems to be our adaptation of the
> [Sprint Review](https://www.scrumguides.org/scrum-guide.html#events-review) event. Key differences include:
> * less focus on the internals of this sprint: that isn't interesting to the company as a whole
> * less focus on the content of next sprint: that is discussed between the Product Owner and stakeholders
> * more focus on displaying the current business value that the Product Team is providing


## Retrospective
* **When:** Last day of the Sprint at 1:30pm
* **Where:** A boardroom
* **Timebox:** 1 hour
* **Participants** Product Team

> The purpose of the Sprint Retrospective is to:
> * Inspect how the last Sprint went with regards to people, relationships, process, and tools;
> * Identify and order the major items that went well and potential improvements; and,
> * Create a plan for implementing improvements to the way the Scrum Team does its work.

Prior to the Sprint Retrospective - any Product Team members may contact the Scrum Master to request items to be
discussed in the Retrospective. The Scrum Master will prepare a general overview of how the last Sprint went, as well as
collate all the items to be discussed in the meeting.

Generally, the Retrospective itself will consist of these activities:
* Any action items raised during the previous Retrospective will be reviewed, and progress on them briefly discussed
* Each Product Team member reflects upon the Sprint just gone, and notes (generally on post-it notes):
  * Positive events (i.e. what the Team did that went well, successes, milestones)
  * Neutral events (i.e. contributing or mitigating factors for sprint performance, changes to roles within the Team)
  * Negative events (i.e. what the Team could improve on, emergencies that may have occurred)
* The Team as a whole will discuss the events noted above, and consider plans of action to address issues raised
* Collate plans of action into a set of action items to be addressed in the next Sprint


## Workshops
* **When:** As required
* **Where:** Any convenient location
* **Timebox:** Varies per session, 2 hours at most
* **Participants** individual Product Team members, external stakeholders

> Workshops are not a standard Scrum ceremony, as time is not reserved for them: they are scheduled as required.
> They are intended for aligning the Product Team's understanding of a problem space with that of the Stakeholders.

Workshops will take the some form of a UX session, depending on what is required most at the time.

Some sessions will require the whole Product Team, and some will require just the Strike Team assigned to a particular
problem space.

See [workshops.md](workshops.md) for a list of the different types of workshops.
