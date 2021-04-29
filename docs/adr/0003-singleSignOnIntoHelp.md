# Single Sign-On into Help

* Status: Proposed
* Deciders: James Leckie, Andy Ryan, Amri Sannang
* Date: 2021-04-27

Technical Story: https://schoolbox.atlassian.net/browse/SA-11850


## Context and Problem Statement

Staff users of Schoolbox instances often forget that they even have a Schoolbox
Help account, and if they do remember they often don't remember their password.

Given that:
* each staff member in a Schoolbox instance should be directly relatable to a
  Schoolbox Help account
* the Help account's username is identical to the instance user's email address

It should be possible to implement a single sign-on system from an instance to
Help; given an instance user's email address, Help should be able to determine
which user is identified by that email address, and then log them in.


## Decision Drivers

* Driving traffic to relevant resources on Schoolbox Help
* Increasing user engagement on Schoolbox Help
* Security of user authentication: single sign-on requests must be at least
  signed with a private key, and rejected if the request payload was tampered


## Considered Options

1. Single sign-on from instance to Help via JWT
2. Implement oAuth, and allow each instance to be an oAuth provider for Help


## Decision Outcome

JWT-based single sign-on was chosen, as implementing Schoolbox as an oAuth
provider and consumer was considered to not give any benefit (and was more
complex than) the JWT-based solution.


## Pros and Cons of the Options

### Single sign-on via JWT

JWTs may be generated, decoded and verified by the existing JWT generation
system, as introduced in SA-9255: no further architectural changes are required.

One issue is that in order for Help to decode a JWT, it needs to know the public
key which corresponds to the private key which signed the JWT. This means that
public keys for each instance will need to be deployed to Help.

### oAuth

This option was not strongly considered at the time of writing: implementing an
oAuth provider and consumer for Schoolbox was written off as too complicated a
solution.

oAuth would also be complicated from a user experience point of view: on
attempting to oAuth into Help, the user may need to choose the instance they are
logging in from (as this may not be determinable from the oAuth request): not
ideal when there are approximately 200 Schoolbox instances.


## Links

* [Original ticket for JWT generation and authentication](https://schoolbox.atlassian.net/browse/SA-9255)
