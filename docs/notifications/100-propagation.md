[< Back to Notifications](README.md)

# Propagation

The propagation sub-system is used to generate events within Schoolbox.
These events will then be handled by the scheduling sub-system.

This sub-system is the bridge between synchronous code (i.e. web requests) and
the asynchronous notifications system.

Technically, events may be created by any Schoolbox process; in practise, they
are usually created by mapper actions.

## Sequence Diagram

![Propagation sequence diagram](Propagation.png "Propagation sequence diagram")

Source: https://drive.google.com/open?id=1TG40pNKxsKMMK-Ij4GQtBz6Rqh9vlTrI

## Technical Description and Developer Guide

### `Schoolbox\Mapper::save/delete/updateAll`

The mapper data persistence actions are the main point of entry into the
notification creation subsystem.
All of them call `Schoolbox\Mapper::raiseNotification` for each model that was
persisted by this call.

#### Developer Notes

As a developer, you shouldn't have to worry too much about the way that this
works.

#### Known Limitations

`raiseNotification` is currently called immediately after all of the models
passed into `save/delete/updateAll` have been persisted.
This can cause issues because domain-layer objects don't always map one-to-one
to data models.

Example:
* a quiz and its questions form a single atomic domain-layer object but
  multiple data models
* the quiz must be persisted first, as the questions cannot exist without the
  quiz
* the quiz's notifications process immediately on its persistence
* the quiz domain object isn't complete without its questions, therefore the
  notification won't be able to rely upon the content of the questions

This can be worked around by deferring and then manually raising notifications.

### `Schoolbox\Mapper::raiseNotification`

This method marshals the current session user, action type (create, update,
delete), model, mapper and changed state (for update actions only) into the
mapper's event propagator's `propagate` method.

#### Developer notes

This method can be overridden; in practise, it's overridden because this mapper:
* doesn't fire notifications at all
* shouldn't fire notifications until its related objects are also persisted

In this case, this method should simply immediately return `true`.

#### Known Limitations

This method will return without propagating if there is no session user; this
is because mapper-based notifications require an effector to have been set.

This means that notifications won't trigger if a mapper save action occurs in a
CLI script or daemon.
