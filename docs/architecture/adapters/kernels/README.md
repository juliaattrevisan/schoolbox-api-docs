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


## Bootstrapping

All kernels have the following responsibilities in their bootstrap phase, as
seen in `\Alaress\Application\Kernel::__construct`.

Some kernel variants may have additional responsibilities.


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

These are set up in `installDependencies`: more information may be found in
the [dependency injection](dependency-injection.md) documentation.


### Set up post-injection dependencies

Some dependencies are only configurable after dependency injection is complete;
these are set up in `postInstallDependencies`.

> Currently this sets up sessions, routing table and modules: this may not be
> required as all of these dependencies could be set up in dependency injection.


## Running a request

After bootstrapping has taken place, a kernel's run method may be called in
order to actually process a request.

(Each variant of the kernel has its own run method, which will be documented
specifically in each variant's documentation.)
