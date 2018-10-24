# Scheduler

The notification system uses the Scheduler to defer the processing of Messages
until a point in time has been reached.

As an example: the digest sub-system uses a Scheduler to defer the sending of
the digest message until the configured time to send digests has been reached.

> TODO: sequence/class/object diagrams?

## Glossary

<dl>
  <dt>Scheduler</dt>
  <dd>
    A generic framework for deferring execution of code until a certain point
    in time has been reached.
  </dd>

  <dt>Event Loop</dt>
  <dd>
    A low-level mechanism that the Scheduler uses to defer execution.
  </dd>

  <dt>Trigger</dt>
  <dd>
    An encapsulation of a set of callbacks to process, and when to process them.
  </dd>

  <dt>Daemon</dt>
  <dd>
    A long-running process which listens to events, and acts upon those events.
  </dd>

  <dt>Dispatcher</dt>
  <dd>
    The interface through which a Scheduler assigns Tasks to be completed.
  </dd>

  <dt>Task</dt>
  <dd>
    The job that the Dispatcher has been assigned to complete.
  </dd>

  <dt>Response</dt>
  <dd>
    The output of a Task, once it has completed execution.
  </dd>

  <dt>Response Handler</dt>
  <dd>
    The object which listens for Responses, and then performs an action based
    on the Response.
  </dd>
</dl>

## Implementation

The Scheduler is an implementation of `\Alaress\Scheduler\AbstractScheduler`.

Note that `AbstractScheduler` implements
`Alaress\WorkerCrew\TaskResponseHandlerInterface`; this means that all
Schedulers may also be set up as the Response Handler for a Daemon.


### Message Consumption

A Scheduler is a Consumer for a Message Broker; it receives Trigger objects
from the Broker, and acknowledges that Message as having been processed once it
has been Dispatched.


### Trigger

The Trigger is an implementation of
`\Alaress\Scheduler\Trigger\TriggerInterface`.

It contains:
* a set of callbacks to execute
* a timestamp to execute those callbacks at
* an event, which provides further context for the callbacks:
  * who effected the event
  * the type of event
  * the object on which the event happened
  * the difference in state before and after the event happened


### Scheduling

The Scheduler's `scheduleNotification` method determines exactly when the
Trigger is processed; it is either processed immediately, or fed into the
`schedule` method to be deferred until the Trigger's timestamp is reached.

The `schedule` method itself uses the Event Loop's Deferred Execution feature
to defer processing of the Trigger. (More information can be found in the
[Event Loop documentation](event-loop.md).)


### Dispatch

The Scheduler will always contain a Dispatcher; it is invoked once the Trigger
is processed.

On dispatch, all of the Trigger's callbacks will be sent to the Dispatcher as
Tasks for the Dispatcher to determine what to do with. (In practise, since the
Scheduler is also a Response Handler, the Scheduler will receive Responses from
the Dispatcher.)

More information on the Dispatcher can be found in the
[Dispatcher documentation](dispatcher.md).


### Response Handling

The Scheduler is also a Response Handler; its `handleResponse` method
determines what happens when the Dispatcher returns a response from the Trigger
that was passed to it.

`handleResponse` may execute arbitrary code; what exactly it does depends on
the purpose of the Scheduler instance.
