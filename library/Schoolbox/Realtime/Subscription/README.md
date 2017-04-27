# Schoolbox\Realtime\Subscription

If you need a new subscription type, create a sub-package that implements the following interfaces:

* `ISubscription`
* `ISubscriptionFactory`

You then only need to invoke the `SubscriptionManager` with a different factory, the abstraction will take care of the rest.

At time of writing, `Redis\*` is the only implementation; previously there was a `Stomp\*` implementation, but it was deprecated in favour of Redis.
