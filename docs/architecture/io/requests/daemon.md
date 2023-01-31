# Daemon Request

One point of input for requests into Schoolbox is via a daemon process: as with
CLI scripts, daemons will generally live in the `tools/` directory of
Schoolbox's code base.

> Daemons are long-running background processes, which may listen to various
> input streams.

As with CLI requests: Schoolbox will perform
[instance routing](../../configuration/instance-routing.md) so that it may
bootstrap the instance's configuration, and then run the CLI request's
configured tool using the [daemon kernel](../../adapters/kernels/daemon.md).
