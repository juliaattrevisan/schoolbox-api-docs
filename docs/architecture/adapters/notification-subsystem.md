# Notification Subsystem Adapter

Ingress into the notification subsystem is coupled quite intimately to
[mappers](mappers.md) and their create/update/delete actions.

## Definition

A notifier class is defined in the same namespace as a mapper: the mapper
simply knows how to invoke the notifier when a record is created, updated or
deleted.

The notifier class should:
- be named `Notifier`
- be in the same namespace as the mapper whose create/update/delete actions it
  listens to
- extend `\Schoolbox\Realtime\Schedule\Module\AbstractModuleNotifier`

See the [Notifier docs](../../notifications/200-scheduling.md#notifier) for more
information on how to define a notifier.

## Instantiation

Developers need not be concerned with the instantiation of notifiers at this
stage: it's all handled magically by the mappers at this stage, by its internal
`raiseNotification` method.

## Usage

Likewise, developers don't need to be concerned with the usage of notifiers:
they will never instantiate them directly.
