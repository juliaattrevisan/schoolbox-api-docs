# Dependency Injection Container

Schoolbox's dependency injection container is intended to provide a single
consistent definition of services which Schoolbox is able to use.

Schoolbox uses the [Aura DI](https://github.com/auraphp/Aura.Di) container: this
container implements the standard [PSR-11](https://www.php-fig.org/psr/psr-11/)
interface for dependency injection containers, and code which relies on an
instance of the container should reference its
`\Psr\Container\ContainerInterface` interface instead of the Aura implementation
where possible.

## Definition

The dependency injection container is set up in each kernel's
`installDependencies` method:

```php
/**
 * This method accepts and returns a concrete `\Aura\Di\Container` instead of a
 * `\Psr\Container\ContainerInterface`: that interface defines only how objects
 * are retrieved from the container, not how they are installed into it.
 */
protected function installDependencies(\Aura\Di\Container $di): \Aura\Di\Container
{
    // inject an object directly
    $di->set($key, $object);

    // lazy-load an object on demand only
    $di->set($key, $di->lazy(function () { ... }));
}
```

## Instantiation

The container is never instantiated manually: instead, it will be accessible
via different methods depending on context.

## Usage

### Kernel

`\Alaress\Application\Kernel::getDependencyInjector` returns the DI container:

```php
require_once SITE_root . '/conf/core/require.php';

/** @var \Alaress\Application\Kernel $kernel */
$di      = $kernel->getDependencyInjector();
$service = $di->get($serviceName);
```

### Modules

`\Alaress\Module\Module::$injector` contains the DI container.

### Controllers

`\Alaress\Web\Controller\Base::$di` contains the DI container, though it
should not be accessed directly: instead,
`\Alaress\Module\Module::buildController` overrides should inject only required
dependencies into the controller's constructor.

### SitePage

`\SitePage::getDi` returns the DI container.

### Mappers

`\Schoolbox\Mapper::getDi` returns the DI container, but it should not be used
unless absolutely necessary for the mapper's operation.

> It is an anti-pattern for mappers to know about Schoolbox's services other
> than the database, as mappers are intended only as an adapter for database
> communication.

### CLI Apps

`\Schoolbox\Application\CliTool\App::getDi` returns the CLI app's DI container,
though like with controllers it should not be accessed directly: instead, the
class which creates the CLI app should inject only required dependencies into
the app's constructor.
