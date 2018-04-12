# Daemon

The notification system uses daemon processes for listening to events within
Schoolbox, and then performing actions based on these changes.

Generally, a daemon listens by subscribing to a message broker; when it
receives an event from the broker, it will summon a worker process to perform a
task.


## Glossary

<dl>
  <dt>Daemon</dt>
  <dd>
    A long-running process which listens to events, and acts upon those events.
  </dd>

  <dt>Message Broker</dt>
  <dd>
    The high-level interface through which Messages are Published and
    Subscribed to.
  </dd>
  
  <dt>Scheduler</dt>
  <dd>
    The interface through which a Daemon can defer Dispatch of a Task until
    another condition has been met.
  </dd>
  
  <dt>Dispatcher</dt>
  <dd>
    The interface through which a Daemon assigns Tasks to Workers.
  </dd>
  
  <dt>Worker Pool</dt>
  <dd>
    A collection of pre-created Workers waiting to be assigned a Task.
  </dd>
  
  <dt>Worker</dt>
  <dd>
    A child process of the Daemon, summoned only to perform Tasks.
  </dd>
  
  <dt>Task</dt>
  <dd>
    The job that a Worker has been assigned. 
  </dd>
</dl>


## Implementation

TODO discuss implementation of daemons


## Usage

TODO discuss how the various parts of daemons work
