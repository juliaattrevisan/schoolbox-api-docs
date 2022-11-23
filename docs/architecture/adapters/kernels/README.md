# Kernel

The Kernel component performs bootstrapping of all incoming requests into
Schoolbox.

All kernels have the following responsibilities, as seen in
`\Alaress\Application\Kernel::__construct`:
* initialize connections to databases (`initialiseExternalConnections`)
    * (may not be required anymore, because a connection was created in
      [instance configuration](../configuration/instance-configuration.md))
* register error, exception and shutdown handling
* set up dependency injection
* set up dependencies which are only configurable after dependency injection
  is complete
    * currently sets up sessions, routing table and modules: may not be required
      as all this could be done in DI above

## Variants

- [Web](web/README.md)
    - [Standard](web/standard.md)
    - [Basic](web/basic.md)
- [CLI](cli.md)
- [Daemon](daemon.md)
