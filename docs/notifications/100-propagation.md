[< Back to Notifications](README.md)

# Propagation

The propagation sub-system is used to generate events within Schoolbox.
These events will then be handled by the scheduling sub-system.

This sub-system is the bridge between synchronous code (i.e. web requests) and
the asynchronous notifications system.

Technically, events may be created by any Schoolbox process; in practise, they
are usually created by mapper actions.

TODO - description/diagrams etc

![Propagation sequence diagram](Propagation.png "Propagation sequence diagram")
Source: https://drive.google.com/open?id=1TG40pNKxsKMMK-Ij4GQtBz6Rqh9vlTrI
