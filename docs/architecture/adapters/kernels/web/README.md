# Web Kernel

The Web Kernel handles all incoming HTTP requests to Schoolbox, once the
relevant instance has been determined and its configuration has been loaded.

## Request handling

All web kernel handled requests will be routed through `www/index.php`.

This script may create and run its web kernel differently, depending on the
request's path and whether it exists in Schoolbox's `www/` folder.


### Requested path exists

If the requested path ends with `.php` _and_ exists in Schoolbox's `www/`
folder, then the `.php` file is included and executed directly.

> For example: an incoming request for path `/portrait.php` matches a file
> `www/portrait.php`.

These `.php` files will create a kernel so that it is bootstrapped, but will not
actually run the kernel; instead, any code after the creation of the kernel will
be run directly.

The fil may create either a [Standard](standard.md) or [Basic](basic.md) kernel,
depending on whether the file is expected to output a UI in its response.

### Requested path does not exist

Otherwise, a new [Standard](standard.md) kernel is created and run: this kernel
knows how to further route requests to their handler.


## Variants

As mentioned above: a different web kernel variant may be created depending on
the request's path.

The following variants exist:

- [Standard](standard.md)
- [Basic](basic.md)
