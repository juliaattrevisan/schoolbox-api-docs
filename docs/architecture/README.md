# Schoolbox Architecture

This provides a very basic overview of the Schoolbox product's architecture.

This should give an overview of the flow of execution of a request incoming to
Schoolbox, and its possible outputs.


## Overview diagram


## Structure

I've attempted to structure the overview diagram and this set of documents
according to the
[clean architecture](https://blog.cleancoder.com/uncle-bob/2012/08/13/the-clean-architecture.html)
pattern (which in itself is influenced by the
[hexagonal architecture](https://en.wikipedia.org/wiki/Hexagonal_architecture_(software))
pattern), even if Schoolbox itself does not always meet the ideals in that
pattern.

The basic principles of this pattern are:
- business-wide policies are in the innermost circle
- frameworks and drivers (input and output) are in the outermost circle
- glue code between IO and policy exists between these two circles
- source code dependencies _always_ point inward, never outward

This document concerns itself mostly with the outer layers of the Schoolbox
application, as that code is used to translate incoming requests into actions.


## Points of ingress

Schoolbox has the following points of ingress; each of these documents will give
an overview about how a request coming through any of these sources is
bootstrapped, routed and processed:
* [Web request](io/requests/web.md)
* [CLI request](io/requests/cli.md)
* [Daemon request](io/requests/daemon.md)
