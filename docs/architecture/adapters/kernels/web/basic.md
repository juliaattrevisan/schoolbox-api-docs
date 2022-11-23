# Web Kernel (Basic)

This web request is designed for when there is no user interface to display.

It is the same as the standard web request kernel, except its dependency
injection container does not set up UI dependencies (e.g. Twig).

> This may become obsolete: the original reasoning for the basic web request
> kernel (performance) is less relevant now that the routing table and module
> configuration are cached.

## Running a request

Basic web request kernels do not have a run method: instead, any code which is
defined after the kernel is created is run automatically.

For example:
```php
require_once SITE_root . '/conf/core/require.php';
$kernel = new \Schoolbox\Application\BasicRequest\Kernel();

// code is run here: any code which _is_ run should result in either:
// * a redirect
// * output of a file's content
```
