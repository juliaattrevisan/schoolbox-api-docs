# Kernel

The Kernel component performs bootstrapping of all incoming requests into
Schoolbox.

The base class for all kernels is `\Alaress\Application\Kernel`.

## Variants

- [Web](web/README.md)
    - [Standard](web/standard.md)
    - [Basic](web/basic.md)
- [CLI](cli.md)
- [Daemon](daemon.md)


## Responsibilities

All kernels have the following responsibilities, as seen in
`\Alaress\Application\Kernel::__construct`.

Some kernel variants may have additional responsibilities.

### Initialize database connection

`initialiseExternalConnections` is used to set up the initial database
connection which other dependencies depend upon to fetch configuration.

> This may not be required anymore, because a connection was created in
> [instance configuration](../configuration/instance-configuration.md) already.


### Register error/exception/shutdown handling

Different kernels will have different processes for handling errors and
exceptions; these are registered with:
- `registerErrorHandling`
- `registerExceptionHandling`
- `registerShutdownHandling`


### Set up dependency injection

All kernels will need to set up a dependency injection container: this container
is used to define the dependencies of the various objects which Schoolbox is
able to make use of.

These are set up in `installDependencies`.


### Set up post-injection dependencies

Some dependencies are only configurable after dependency injection is complete;
these are set up in `postInstallDependencies`.

> Currently this sets up sessions, routing table and modules: this may not be
> required as all of these dependencies could be set up in dependency injection.
