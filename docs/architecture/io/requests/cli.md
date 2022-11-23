# CLI Request

One point of input for requests into Schoolbox is via the execution of a command
line script: these scripts will generally live in the `tools/` directory of
Schoolbox's code base.

Schoolbox will perform
[instance routing](../../configuration/instance-routing.md) so that it may
bootstrap the instance's configuration, and then run the CLI request's
configured tool using the [CLI kernel](../../adapters/kernels/cli-kernel.md).
