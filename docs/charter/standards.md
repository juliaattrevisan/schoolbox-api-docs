# Schoolbox Development Standards

> TODO this is very much a WIP

> “It’s not what you preach, it’s what you tolerate.”


## Purpose

The purpose of this document is to ensure that all members of the Schoolbox development team consistently contribute code that is of an acceptable standard for release.


## Audience

This document is intended for:
* Developers, so that:
  * They know what responsibilities they have when contributing code
  * They can quickly contribute code changes that will be accepted into Schoolbox
  * They can continue to grow their craft, by following standards and knowing the reasoning behind them
* Reviewers and Quality Assurance, so that:
  * They can quickly verify that a proposed code change is of an acceptable standard
* Tech Leads and other leadership positions, so that:
  * They can communicate the standards and their importance, and ideally ensure that following them becomes second nature 


## Standards

Each standard needs to mention:
* The reasoning for why it exists
* What responsibilities a team member has at what step:
  * Development
  * Review
  * QA


### Keep master and develop PRs in sync, if at all possible

This standard exists to ensure that subsequent code changes can be applied against both master and develop.

#### Responsibilities

Developers need to create identical (or as close to as possible) PRs against master and develop.
If the issue's description calls for different functionality for develop, then the new functionality is to be developed
as a separate PR that is then  the develop PR.

Reviewers and Quality Assurance need to ensure that the develop and master PRs are in sync before merging them.
If they aren't in sync, then new PR(s) should be created that are.


### Safe output of unsafe data

This standard exists as user-provided inputs cannot be trusted to be safe when output in all contexts.

#### Output contexts

The following output contexts exist:

##### HTML (escaped)

##### HTML (purified)

##### JavaScript

##### JSON

##### CSS

##### Raw (no escaping)


#### Responsibilities

Developers and reviewers must both ensure that they use the appropriate escaping for the context in which they're outputting it.


### SOLID principles, and why they’re important

#### Principles

##### Single Responsibility
> Case study in what to not do: SitePage

##### Open/Closed
Interfaces are an example of open for extension, but closed for modification

##### Liskov Substitution
Overridden method’s input params must be as or less restrictive;
Overridden method’s return params must be as or more restrictive

##### Interface Segregation
No class should have to implement methods it doesn’t use. 
> Case study: my first implementation of a revocation queue?

In practise: keep interfaces concise, consider if your new method really needs to go in the same interface or if it can go into a different one

##### Dependency Injection
A class shouldn’t be instantiating its own dependencies, they should be passed in
> Case study in what not to do: User mapper’s loadParents method. This could also be a case study in single responsibility?

### Unit tests

#### Best practises

##### Use data providers

##### Don’t reuse data providers?

##### Pass all inputs into a tested method into a data provider
