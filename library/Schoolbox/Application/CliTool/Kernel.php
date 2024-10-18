<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Application\CliTool;

use Alaress\Lumberjack;
use Aura\Di\Exception;
use Aura\Di\Exception\ContainerLocked;
use Aura\Di\Exception\ServiceNotFound;
use Aura\Di\Exception\ServiceNotObject;
use ErrorException;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Schoolbox\Application\CliKernelAbstract;
use Schoolbox\Config\Loader as ConfigLoader;

/**
 * This Kernel is designed for running CLI scripts/tools that are one-offs, for example:
 * * migration/update scripts
 * * jobs/tasks
 *
 * By default it will append logs to STDERR, unless a file is specified using the -l CLI option
 */
class Kernel extends CliKernelAbstract
{
    // Command line short options recognised by this kernel
    private const OPT_SHORT_OUT_STREAM = 'o:'; // CLI option to allow output to be written to a file, rather than STDOUT
    private const OPT_SHORT_ERR_STREAM = 'l:'; // CLI option to allow logs to be written to a file, rather than STDERR
    public const OPT_SHORT_PERFORM    = 'p';  // CLI option to indicate the App short be run in 'perform' mode

    // By default, all apps run in 'dry-run' mode, unless either:
    // 1. this option is supplied on the command line, or
    // 2. the kernel is constructed from a script that instructs the kernel to 'forcePerform'
    public const OPT_LONG_PERFORM = 'perform';

    /** @var App */
    private $app;

    /**
     * These control the kernel and default logger
     *
     * Array values will be:
     * * bool   - if option does not accept a value
     * * string - if option does accept a value
     * * array  - if option is specified multiple times
     *
     * @var bool[]|string[]|array[]
     */
    private $kernelOpts = [];

    /**
     * These are passed back to the CLI app
     *
     * Array values will be:
     * * bool   - if option does not accept a value
     * * string - if option does accept a value
     * * array  - if option is specified multiple times
     *
     * @var array
     */
    private $appOpts;

    /**
     * The resource to which the App should stream output
     *
     * @var resource
     */
    private $outStream;


    /**
     * @param App $app
     * @param array $options Keys are one of the self::OPTION_X constants
     * @param bool $bypassMaintenanceModeCheck  Our upgrades sometimes prevent scripts from running unless this is set to true
     * @throws InvalidArgumentException
     * @throws Exception
     * @throws ContainerLocked
     * @throws ServiceNotFound
     * @throws ServiceNotObject
     * @throws \RuntimeException
     * @throws \Schoolbox\External\Exception
     * @throws \UnexpectedValueException
     */
    public function __construct(App $app, array $options = [], bool $bypassMaintenanceModeCheck = false)
    {
        $this->parseCommandLineOptions($app);

        parent::__construct($options[self::OPTION_LEVEL] ?? null, $bypassMaintenanceModeCheck);

        $this->validateOptions($options);
        $this->app = $app;
        \error_reporting($options[self::OPTION_ERRORS] ?? E_ALL ^ E_NOTICE);
        $this->checkServerApiEnvironment();
        $this->checkSystemUserRootStatus($app);
        $this->outStream = $this->getOutputStream();
    }


    /**
     * Run the app
     *
     * @param null|bool $forcePerform Allow invoking script to force the App to run in 'perform' mode
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    public function runApp(bool $forcePerform = false): void
    {
        $requiredUser = $this->app->getRequiredProcessUser();
        if ($requiredUser !== null) {
            $actualUser = posix_getpwuid(posix_geteuid())['name'];
            if ($requiredUser !== $actualUser) {
                $this->logger()->error("Invalid process user. Required '$requiredUser', Actual: '$actualUser'");
                $scriptAndArgs = implode(' ', array_merge([$this->app->getToolFilenameAbsolute()], $this->app->getToolArguments()));
                $i = INSTANCE_ID;
                $this->logger()->notice("Maybe try something like:\n\tsudo -u $requiredUser INSTANCE_ID=$i $scriptAndArgs");
                exit(self::EXIT_STATUS_KERNEL_ERROR);
            }
        }

        // Instrument here:
        // presume that the invocation is in the last node of the backtrace
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        $origin    = array_pop($backtrace);
        $this->sentryTransactionManager->startTransactionFromCommandLineTool($origin['file'], $_SERVER);

        $perform = $forcePerform || $this->getKernelOpt(self::OPT_SHORT_PERFORM) || $this->getKernelOpt(self::OPT_LONG_PERFORM);
        $this->app->setup($this->appOpts, $this->getDependencyInjector(), $this->outStream, $this->logger(), $perform);
        $this->app->run();
    }


    /**
     * Handle specific kinds of warnings differently
     *
     * @param int    $errNum
     * @param string $errStr
     * @param string $errFile
     * @param int    $errLine
     * @throws ErrorException
     */
    protected function onWarning($errNum, $errStr, $errFile, $errLine): void
    {
        throw new ErrorException($errStr, 0, $errNum, $errFile, $errLine);
    }


    /**
     * Creates a default logger, suitable for this Kernel
     *
     * @return LoggerInterface
     * @throws \Aura\Di\Exception\ServiceNotFound
     * @throws \UnexpectedValueException
     */
    protected function createDefaultLogger(): LoggerInterface
    {
        // Where should we send logs?
        $outFile = $this->getKernelOpt(self::OPT_SHORT_ERR_STREAM);
        if (null !== $outFile) {
            $outStream = \fopen($outFile, 'ab');
            if (!$outStream) {
                throw new \UnexpectedValueException("Unable to write logs to '$outFile'");
            }
        } else {
            $outStream = STDERR;
        }

        $level = $this->getLoggingLevel($this->getKernelOpt(self::OPT_SHORT_VERBOSITY));

        // Derive the list of fields to prepend to log items
        $fields = Lumberjack\Logger::getAvailableFields();
        foreach ($fields as $key => $field) {
            if (!\array_key_exists($field, $this->kernelOpts)) {
                unset($fields[$key]);
            }
        }

        $porcelain = \array_key_exists(self::OPT_LONG_PORCELAIN, $this->kernelOpts);
        $logger    = new Lumberjack\Logger($outStream, null, $level, $fields, $porcelain);
        $this->addSentryHandler($logger);

        return $logger;
    }


    /**
     * Checks that the App is being run by a user with root status equal to what the code specifies as being appropriate
     *
     * @param App $app
     * @throws \UnexpectedValueException
     */
    private function checkSystemUserRootStatus(App $app): void
    {
        $runAsRoot = $app->runAsRoot();

        // If not specified, or NULL, we are done.
        if (null === $runAsRoot) {
            return;
        }
        $isRoot = $this->isRunAsSuperuser();

        $prefix = strpos(get_class($app), "class@anonymous") === 0
            ? "The anonymous App used by script '{$app->getToolFilename()}'"
            : sprintf("The App '%s'", get_class($app));

        if ($runAsRoot && !$isRoot) {
            throw new \UnexpectedValueException(sprintf(
                "$prefix requires to be run as system 'root' user, current user: '%s'",
                posix_getpwuid(posix_geteuid())['name']
            ));
        }
        if (!$runAsRoot && $isRoot) {
            $this->logger()->warning(
                "$prefix is not designed to be run as root but is being run as root. " .
                "If this is because this App has been invoked by an update script, this is probably fine, " .
                "as the update/deployment system has post-execution tools to correct file ownership. " .
                "If you are invoking this tool manually, you will need to manually fix the ownership of any files " .
                "this app has created."
            );
        }
    }


    /**
     * Returns the resource to which the App should stream its output
     *
     * @return resource
     * @throws InvalidArgumentException
     */
    private function getOutputStream()
    {
        // Order of preference:
        // 1. Command line option specifying a file to write to
        // 2. STDOUT
        $outfile = $this->getKernelOpt(self::OPT_SHORT_OUT_STREAM);
        if (null === $outfile) {
            return STDOUT;
        }
        $outStream = \fopen($outfile, 'wb');
        if (!$outStream) {
            throw new InvalidArgumentException("Unable to write output to file: '$outfile'");
        }
        return $outStream;
    }


    /**
     * @inheritdoc
     */
    protected function getKernelShortOpts(): array
    {
        return \array_merge(
            parent::getKernelShortOpts(),
            [
                self::OPT_SHORT_OUT_STREAM,
                self::OPT_SHORT_ERR_STREAM,
                self::OPT_SHORT_PERFORM,
            ]
        );
    }


    /**
     * @inheritdoc
     */
    protected function getKernelLongOpts(): array
    {
        return \array_merge(parent::getKernelLongOpts(), [self::OPT_LONG_PERFORM]);
    }


    /**
     * Parses the command line options, and splits into distinct sets to be used by the Kernel and App respectively.
     *
     * /tool.php -vvv -p -x=abc -x
     *
     * @param App $app
     * @throws InvalidArgumentException
     */
    private function parseCommandLineOptions(App $app): void
    {
        // Ensure app not attempting to use options that are already in use by kernel
        $this->assertValidAppOptions($app, true);
        $this->assertValidAppOptions($app, false);

        $appShortOpts    = $app->getShortOpts();
        $appLongOpts     = $app->getLongOpts();
        $kernelShortOpts = $this->getKernelShortOpts();
        $kernelLongOpts  = $this->getKernelLongOpts();

        // Get all opts from command line
        $allOpts = \getopt(
            \implode('', \array_merge($appShortOpts, $kernelShortOpts)),
            \array_merge($appLongOpts, $kernelLongOpts)
        );

        // Extract the kernel options
        foreach (\array_merge($kernelShortOpts, $kernelLongOpts) as $kernelOption) {
            $trimmedKernelOption = \rtrim($kernelOption, ':');
            if (\array_key_exists($trimmedKernelOption, $allOpts)) {
                $this->kernelOpts[$trimmedKernelOption] = $allOpts[$trimmedKernelOption];
            }
        }

        // Extract the app options
        $this->appOpts = [];
        foreach (\array_merge($appShortOpts, $appLongOpts) as $appOption) {
            $trimmedAppOption = \rtrim($appOption, ':');
            if (\array_key_exists($trimmedAppOption, $allOpts)) {
                $this->appOpts[$trimmedAppOption] = $allOpts[$trimmedAppOption];
            }
        }
    }


    /**
     * Checks that a CLI app's options are valid and are not reserved by the kernel
     *
     * @param App  $app
     * @param bool $isShort  Whether we are checking short or long options
     * @throws InvalidArgumentException
     */
    private function assertValidAppOptions(App $app, bool $isShort): void
    {
        $pattern    = $isShort ? '/^[a-zA-Z]:{0,2}$/' : '/^[a-zA-Z]+[a-zA-Z-]*[a-zA-Z]*:{0,2}$/';
        $appOpts    = $isShort ? $app->getShortOpts() : $app->getLongOpts();
        $kernelOpts = $isShort ? $this->getKernelShortOpts() : $this->getKernelLongOpts();
        $type       = $isShort ? 'short' : 'long';
        $processed  = [];
        foreach ($appOpts as $idx => $appOpt) {
            if (!\preg_match($pattern, $appOpt)) {
                throw new InvalidArgumentException("Invalid $type option detected at index $idx: '$appOpt'");
            }
            $trimmed = \rtrim($appOpt, ':');
            if (\in_array($trimmed, $processed, true)) {
                throw new InvalidArgumentException("Duplicate $type option detected at index $idx: '$trimmed'");
            }
            foreach ($kernelOpts as $kernelOpt) {
                if (\rtrim($kernelOpt, ':') === $trimmed) {
                    throw new InvalidArgumentException("Invalid app $type option collides with kernel $type option: '$trimmed'");
                }
            }
            $processed[] = $trimmed;
        }
    }


    /**
     * Returns a kernel's command line option's value
     *
     * If option does accept values:
     *     returns that value, or NULL if not specified
     * If option does not accept values:
     *     returns true if specified once, or array if specified multiple times
     *
     * @param string $name Must have colon chars required/optional decoration still appended.
     * @return mixed
     */
    private function getKernelOpt(string $name)
    {
        // Does this option accept values?
        if (\preg_match('/:$/', $name)) {
            // return the value
            return $this->kernelOpts[\rtrim($name, ':')] ?? null;
        }

        // Does not accept values, and not specified
        if (!\array_key_exists($name, $this->kernelOpts)) {
            return false;
        }

        // Does not accept values, but specified at least once.
        return \is_array($this->kernelOpts[$name]) ? $this->kernelOpts[$name] : true;
    }
}
