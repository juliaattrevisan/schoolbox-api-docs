# Web Kernel

The Web Kernel handles all incoming HTTP requests to Schoolbox, once the
relevant instance has been determined and its configuration has been loaded.

The entrypoint for all web kernel handled requests is `www/index.php`.

A different web kernel variant may be created depending on the request's path:
* if the path ends with `.php` _and_ exists in Schoolbox's `www/` folder:
  the `.php` file is executed directly
  * for example: an incoming request for path `/portrait.php` matches a file
    `www/portrait.php`
  * these `.php` files will create a kernel so that it is bootstrapped, but will
    not actually run the kernel
  * these `.php` files may create either a [Standard](standard.md) or
    [Basic](basic.md) kernel, depending on whether the `.php` file will output a
    UI in its response
* otherwise, a new [Standard](standard.md) kernel is created and run: this
  kernel knows how to further route requests to their handler


## Variants

- [Standard](standard.md)
- [Basic](basic.md)
