# Issue Type Definitions

## Epic
Represents a problem to be solved. Has a problem statement in the format:
* As a \<type of user>
* I want to \<do this>
* so that I \<can achieve this goal>
 
Internal projects such as refactors have to conform to the same constraints as any feature-based solution: they have to have a problem, goal, and articulable value. 

Everything that goes into an epic needs to have cohesion and purpose; they are not a catch-all song-that-never-ends where extra tasks are forever being added - the epic represents the scope of what’s being developed. Epics do not include bugs except those identified as blocking the solution’s release.

## Activity
Something foundational that needs to be done with no code merge required, though prototype code may be written. Often collaborative, so does not necessarily have a single assignee. Often generated as the output of [Workshops](workshops.md)

## Story
Describes a functional behaviour of the system from a user’s perspective. This behavior is described using a statement in the format:
* As a \<type of user>
* I want to \<do this>
* so that I \<can achieve this goal>

Used to determine whether a task or feature works the way it was designed. Specifically, stories do not prescribe a solution: they are a user-centric description of the outcome that is to be achieved. It is a deliverable unit of work.
Delivers perceivable value to stakeholders.

## Task
Code needs writing, but does not deliver _perceivable_ value to stakeholders (for example, refactoring). It is a deliverable unit of work.

## Sub-task
Weak entity. Exists only for helping developers break down / conceptualise a task or story (typically to generate multiple separate PRs). It is not an atomic unit of work; that responsibility lies with the task or story within which the sub-task exists. Sub-tasks are not to be created during Sprint; they are still part of the refinement process. Sub-tasks are not subject to QA; the parent task or story waits in review until all the sub-tasks are merged, then moves to QA.

## Bug
Broken intended functionality, whether by preventing a user from using part of the system as per our intended functionality, or allows a user to do something that they shouldn't be able to do. 

This is distinct and separate from “buggish” tasks which are when our intended functionality&mdash;potentially previously-decided or purposefully shortcutted, however flawed&mdash;turns out to be bad, or we decide we want to redesign something (including code).
For UI and non-functionals, our “intentions” need to be governed by our Definition of Done.
