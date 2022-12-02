# Sitepage Controller

Sitepage controllers are invoked by either:
- [Basic kernels](../kernels/web/basic.md)
- [Standard kernels handled by Sitepage](../kernels/web/standard.md#sitepage)

All Sitepage controllers exist in the `www/` directory.

```php
require_once SITE_root . '/conf/core/require.php';

// for a Basic kernel:
$kernel = new \Schoolbox\Application\BasicRequest\Kernel();

// or for a Standard kernel:
$kernel = new \Schoolbox\Application\WebRequest\Kernel();

// any code beneath this point is run as is:
// * a Basic kernel should result in either a redirect, or output of a file's
//   content
// * a Standard kernel should output a PHPLib template
```
