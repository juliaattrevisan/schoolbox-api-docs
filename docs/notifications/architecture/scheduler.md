# Scheduler

The notification system uses the Scheduler to defer the processing of Messages
until a point in time has been reached.

As an example: the digest sub-system uses a Scheduler to defer the sending of
the digest message until the configured time to send digests has been reached.

> TODO: complete documentation

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
</dl>

## Implementation

The Scheduler is an implementation of `\Alaress\Scheduler\AbstractScheduler`.


### Event Loop

The Scheduler uses the Event Loop's Deferred Execution to defer processing of
Messages until their time to process has been reached.

More information can be found in the [Event Loop documentation](event-loop.md).
