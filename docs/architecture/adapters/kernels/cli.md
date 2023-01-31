# CLI Kernel

The CLI kernel class `\Schoolbox\Application\CliTool\Kernel` is designed to
handle command-line requests: in addition to the standard web request kernel,
it is able to parse command-line options.

The CLI kernel modifies behavior in the following ways:
- no request time limit
- no mapper cache
- the current time is based on the actual current time, not the time of
  receiving the request

## Running a request

The CLI kernel will run an instance of `\Schoolbox\Application\CliTool\App`.

```php
$app    = new class extends \Schoolbox\Application\CliTool\App {
    ...
};
$kernel = new \Schoolbox\Application\CliTool\Kernel($app, $options);
$kernel->runApp();
```
