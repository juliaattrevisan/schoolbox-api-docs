# Testing Schoolbox

## Unit Tests

Let's be honest, there isn't a huge amount of unit test coverage in Schoolbox.
But whatever tests we _do_ have need to be passing.

### CLI

To run tests in your vagrant, it's as simple as
```bash
$ vagrant ssh
$ cd /vagrant
$ vendor/bin/phpunit
```

This will run all the tests and provide you the report. 

### In PHPStorm

PHPStorm's integration with PHPUnit and XDebug provides a substantial number
of useful features you can take advantage of (and renders your tests out in a
very pretty fashion.)

The hardest part is configuring Storm - after that it's a breeze. You can find
the [official documentation here](https://confluence.jetbrains.com/display/PhpStorm/Running+PHPUnit+tests+over+SSH+on+a+remote+server+with+PhpStorm)
but here's an outline of the steps you need to undertake to make it work:

1. Open **File &rsaquo; Settings** (Ctrl + Alt + S)
2. Drill down to **Language &amp; Frameworks &rsaquo; PHP &rsaquo; Test Frameworks**
3. Click the &plus; to add a Framework
4. Select **PHPUnit by Remote Interpreter**
5. If your vagrant is not yet set up as an interpreter:
    1. Click the tot with the exclamation mark on it to open the Interpreters dialog
    2. Click the &plus; to add a new Interpreter
    3. Select **From Docker, Vagrant, VM, Remote...**
    4. Select the **Vagrant** option
    5. Storm will check connectivity - once it's done hit Ok
    6. Click **Ok** to save
6. Select your vagrant Interpreter and click **Ok**
7. In the **PHPUnit library** section:
    * Select **Use Composer autoloader**
    * Path to script: `/vagrant/vendor/autoload.php`
8. In the **Test Runner** section:
    * Default configuration file: `/vagrant/phpunit.xml`
    * Default bootstrap file: `/vagrant/test/bootstrap/unit.php`
9. Click **Ok** - you're now configured ready to go!

From here, you can now open a test file, right-click and select **Run...** -
Storm should then invoke your test. You can also select **Debug** or
**Run with Coverage** for different execution styles (note that these require
your vagrant's xdebug to be correctly configured...which it _should_ be by
default.)