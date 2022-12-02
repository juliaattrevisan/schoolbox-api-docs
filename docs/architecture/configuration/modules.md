# Module Configuration

Schoolbox's modules are configured in `conf/modules`: these configurations each
load a module's routes at a given base URL.

Modules themselves are described in [their own section](../adapters/modules.md).

## Core modules

Core modules exist directly within `conf/modules`: these modules are available
to all instances.

## Custom modules

Custom modules exist within `conf/modules/Custom`: these modules are only
available to an instance whose configured school code matches the sub-folder
name, e.g. `conf/modules/Custom/Help` is available only to instances with the
school code `Help`.

## Configuration

Each module configuration class implements the
`\Config\Modules\ModuleConfigInterface` interface: this defines a `configure`
method.

This method is responsible for loading one or more modules, whose controller
actions will be loaded when a web request is received for their path.

In practice: all module configuration classes extend `\Config\ModuleLoader`, and
load modules via that class' `loadModule` method:

```php
namespace Config\Modules;

class ModuleConfig extends \Config\ModuleLoader
{
    public function configure(\Psr\Container\ContainerInterface $di): void
    {
        // The class name of the module to load
        $moduleClassName = \Schoolbox\My\Module::class;

        // The base path for this module's controller's actions:
        // Schoolbox will register routes for all this module's routes with this
        // as the base path
        $basePath = '/base/path';

        // Load the module at the given base path
        $this->loadModule($di, $moduleClassName, $basePath);
    }
}
```
