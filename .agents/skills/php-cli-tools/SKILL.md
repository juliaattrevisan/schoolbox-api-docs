---
name: php-cli-tools
description: Use when developing command-line (CLI) tools in PHP.
---

# PHP command-line tools overview

Schoolbox command-line tools must be developed in PHP, if they are to have access to the same objects as Schoolbox's web platform.

# Basic format

The tool's base executable:
- must be in the `tools/` directory, and preferably within a sub-directory whose name reflects the module for which the tool is for
- must have its executable flag set
- must have the `.php` extension
- should be named in `kebab-case`, though `camelCase` is historically acceptable

# Script template

The following should be used as a basic template for a script in the `tools/${subdirectory}` directory:

```php
#!/usr/bin/env php
<?php

/**
 * @copyright Copyright ${YEAR}, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

use Schoolbox\Application\CliTool;

// This require of `conf/core/require.php` presumes the script is in
// `tools/${subdirectory}`: if it is elsewhere, adjust the require
// accordingly
require_once __DIR__ . '/../../conf/core/require.php';

$kernel = new CliTool\Kernel(new class extends CliTool\BaseApp {
    public function run(): void
    {
        // Implement the tool itself here
    }
});
$kernel->runApp();
```

Note that the implementation of `\Schoolbox\Application\CliTool\BaseApp` is defined as an inline class in this template. This is acceptable for small scripts, but larger scripts should be defined within their own file.

# App template

As mentioned in the template above, app classes should extend `\Schoolbox\Application\CliTool\BaseApp`.

```php
<?php

/**
 * @copyright Copyright ${YEAR}, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace ${namespace};

use Schoolbox\Application\CliTool\BaseApp;

class ${classname} extends BaseApp
{
    public function run(): void
    {
        // Implement the tool itself here
    }

    protected function getName(): string
    {
        // return a name for the script:
        // this will be output in its help text
    }

    protected function getDescription(): string
    {
        // return a description for the script:
        // this will be output in its help text
    }

    protected function setOptions(): self
    {
        // add calls to $this->optionCollection->add() to add options
        return $this;
    }

    protected function hasPerformMode(): bool
    {
        // return true if this script should have a perform mode,
        // which is activated by `-p` or `--perform`:
        // false otherwise
    }
}
```

# Migrating apps to use new base class

As mentioned above, apps should now extend `\Schoolbox\Application\CliTool\BaseApp`: however, many scripts still extend a superclass `\Schoolbox\Application\CliTool\App`.

The main purpose of `BaseApp` is to provide a consistent method for outputting help text and command-line options.

In order to migrate an app which still extends `App`:
- extend `BaseApp` instead
- search for a `printHelp` or similarly named method (there is no consistent name for this)
- note what this method echoes: it will be needed for filling in some abstract methods
- populate the following abstract methods:
  - `getName`: find something which looks like a name in help content, and return it here
  - `getDescription`: find something which looks like a name in help content, and return it here
  - `setOptions`:
    - find all options defined in `getShortOpts` and `getLongOpts`
      - excluding output help options: this is auto-handled
    - attempt to match them up with something mentioned in the description
    - add a line thus:
      ```php
      $this->addOption(${shortOptOrNull}, ${longOptOrNull}, ${description});
      ```
- search through `run` and anything it may call, and populate `hasPerformMode`:
  - if there is a call to the `perform` method, return `true`; otherwise `false`
