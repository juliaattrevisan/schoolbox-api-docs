<?php
/**
 * @copyright   Copyright 2019, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Application\CliTool;

use Alaress\Lumberjack;
use ErrorException;
use InvalidArgumentException;
use Monolog;
use Psr\Log\LoggerInterface;
use Schoolbox\Application\CliKernelAbstract;

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
    private const OPT_SHORT_VERBOSITY  = 'v';  // CLI option that verbosity, unless overridden. @see Lumberjack\Logger::FIELD_LEVEL
    private const OPT_SHORT_PERFORM    = 'p';  // CLI option to indicate the App short be run in 'perform' mode

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
    private $kernelOptions;

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
    private $appOptions;

    /**
     * The resource to which the App should stream output
     *
     * @var resource
     */
    private $outStream;

    /** @var null|int */
    private $minLogLevel;


    /**
     * @param App      $app
     * @param int|null $minLogLevel Allow invoking script to force the App to not log below this level
     * @param int|null $errorLevel
     * @throws InvalidArgumentException
     * @throws \Aura\Di\Exception
     * @throws \Aura\Di\Exception\ContainerLocked
     * @throws \Aura\Di\Exception\ServiceNotFound
     * @throws \Aura\Di\Exception\ServiceNotObject
     * @throws \RuntimeException
     * @throws \Schoolbox\External\Exception
     * @throws \UnexpectedValueException
     */
    public function __construct(App $app, int $minLogLevel = null, int $errorLevel = E_ALL ^ E_NOTICE)
    {
        $this->app         = $app;
        $this->minLogLevel = $minLogLevel;
        \error_reporting($errorLevel);
        $this->checkServerApiEnvironment();
        $this->parseCommandLineOptions($app);
        $this->outStream = $this->getOutputStream();

        parent::__construct();
    }


    /**
     * Run the app
     *
     * @param null|bool $forcePerform Allow invoking script to force the App to run in 'perform' mode
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    public function runApp(bool $forcePerform = false): void
    {
        $perform = $forcePerform || $this->getKernelOpt(self::OPT_SHORT_PERFORM) || $this->getKernelOpt(self::OPT_LONG_PERFORM);
        $this->app->setup($this->appOptions, $this->getDependencyInjector(), $this->outStream, $this->logger(), $perform);
        $this->app->run();
    }


    /**
     * Load the core configuration file(s)
     *
     * @return void
     */
    protected function loadConfiguration(): void
    {
        // TODO remove this once SA-8248 is merged
    }


    /**
     * Set the include path
     *
     * @return void
     */
    protected function setIncludePath(): void
    {
        // TODO remove this once SA-8248 is merged
    }


    /**
     * Normal (ie non-Kernel) class loading
     */
    protected function registerAutoLoading(): void
    {
        // TODO remove this once SA-8248 is merged
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

        // At what level should we log? Order of preference:
        // 1. Kernel construction
        // 2. Log level command line option
        // 3. Verbosity command line option
        if ($this->minLogLevel) {
            $level = $this->minLogLevel;
        } else {
            $commandLineLevel = $this->getKernelOpt(Lumberjack\Logger::FIELD_LEVEL);
            $level            = $commandLineLevel !== null
                ? (int)$commandLineLevel
                : $this->getLoggingLevelFromVerbosity();
        }

        $fields    = \array_keys($this->kernelOptions);
        $porcelain = $this->getKernelOpt(Lumberjack\Logger::FIELD_PORCELAIN);
        $logger    = new Lumberjack\Logger($outStream, null, $level, $fields, $porcelain);
        $this->addRavenHandler($logger);

        return $logger;
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
     * Determine logging level via verbose setting
     * verbose is set by -vvv.  Each v represents a value in an array.
     * So -v is level 1, -vv is level 2, -vvv is level 3
     *
     * @return int
     * @todo merge and lawn mow this and Daemon\Kernel::getLoggingLevel()
     * @todo need to delete the intermediate 'verbose' option, instead just use 'v'
     */
    private function getLoggingLevelFromVerbosity(): int
    {
        $v = $this->getKernelOpt(self::OPT_SHORT_VERBOSITY);

        if (!$v) {
            $threshold = 0;
        } elseif (!\is_array($v)) {
            $threshold = 1;
        } else {
            $threshold = \min(3, \count($v));
        }

        $limits = [
            0 => Monolog\Logger::CRITICAL,
            1 => Monolog\Logger::NOTICE,
            2 => Monolog\Logger::INFO,
            3 => Monolog\Logger::DEBUG,
        ];
        return $limits[$threshold];
    }

    private function getKernelShortOpts(): array
    {
        return [self::OPT_SHORT_OUT_STREAM, self::OPT_SHORT_ERR_STREAM, self::OPT_SHORT_VERBOSITY, self::OPT_SHORT_PERFORM];
    }

    private function getKernelLongOpts(): array
    {
        return [
            self::OPT_LONG_PERFORM,
            Lumberjack\Logger::FIELD_MEM,
            Lumberjack\Logger::FIELD_TRUE_MEM,
            Lumberjack\Logger::FIELD_PID,
            Lumberjack\Logger::FIELD_LEVEL,
            Lumberjack\Logger::FIELD_PORCELAIN,
        ];
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
        $this->kernelOptions = [];
        foreach (\array_merge($kernelShortOpts, $kernelLongOpts) as $kernelOption) {
            $trimmedKernelOption = \rtrim($kernelOption, ':');
            if (\array_key_exists($trimmedKernelOption, $allOpts)) {
                $this->kernelOptions[$trimmedKernelOption] = $allOpts[$trimmedKernelOption];
            }
        }

        // Extract the app options
        $this->appOptions = [];
        foreach (\array_merge($appShortOpts, $appLongOpts) as $appOption) {
            $trimmedAppOption = \rtrim($appOption, ':');
            if (\array_key_exists($trimmedAppOption, $allOpts)) {
                $this->appOptions[$trimmedAppOption] = $allOpts[$trimmedAppOption];
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
            return $this->kernelOptions[\rtrim($name, ':')] ?? null;
        }

        // Does not accept values, and not specified
        if(!\array_key_exists($name, $this->kernelOptions)){
            return false;
        }

        // Does not accept values, but specified at least once.
        return \is_array($this->kernelOptions[$name]) ? $this->kernelOptions[$name] : true;
    }


    /**
     * @throws InvalidArgumentException
     * @throws \Aura\Di\Exception
     * @throws \Aura\Di\Exception\ContainerLocked
     * @throws \Aura\Di\Exception\ServiceNotFound
     * @throws \Aura\Di\Exception\ServiceNotObject
     * @throws \RuntimeException
     */
    protected function installDependencies(): void
    {
        parent::installDependencies();
        $this->initModules();
    }
}
