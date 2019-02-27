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

    // Command line options recognised by this kernel
    private const OPT_OUT_STREAM = 'o:'; // CLI option to allow output to be written to a file, rather than STDOUT
    private const OPT_ERR_STREAM = 'l:'; // CLI option to allow logs to be written to a file, rather than STDERR
    private const OPT_VERBOSITY  = 'v';  // CLI option that verbosity, unless overridden by Lumberjack\Logger::FIELD_LEVEL

    // These control the kernel and default logger
    private $kernelOptions;

    // These are passed back to the CLI app
    private $appOptions;

    // The stream to which the App will be instructed to write output to
    private $outStream;


    /**
     * @param string[] $appShortOpts
     * @param string[] $appLongOpts
     * @throws \Aura\Di\Exception
     * @throws \Aura\Di\Exception\ContainerLocked
     * @throws \Aura\Di\Exception\ServiceNotFound
     * @throws \Aura\Di\Exception\ServiceNotObject
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     * @throws \Schoolbox\External\Exception
     * @todo the apps's options should be passed into runApp(), not here.
     */
    public function __construct(array $appShortOpts = [], array $appLongOpts = [])
    {
        $this->parseCommandLineOptions($appShortOpts, $appLongOpts);
        $this->setErrorReporting();

        parent::__construct();

        $this->checkServerApiEnvironment();
        $this->outStream = $this->getOutputStream();
    }


    /**
     * Run the app, passing it back any command line options passed into the Kernel's constructor
     *
     * @param App $app
     * @throws \Aura\Di\Exception\ServiceNotFound
     * @todo pass in the app's CLI options here, using $argv to parse. Trigger errors if invalid args detected.
     */
    public function runApp(App $app): void
    {
        $app->setup($this->getDependencyInjector(), $this->outStream, $this->logger());
        $app->run($this->appOptions);
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
        $outFile = $this->getKernelOpt(self::OPT_ERR_STREAM);
        if ($outFile) {
            $outStream = \fopen($outFile, 'ab');
            if (!$outStream) {
                throw new \UnexpectedValueException("Unable to write logs to '$outFile'");
            }
        } else {
            $outStream = STDERR;
        }

        $level     = (int)$this->getKernelOpt(Lumberjack\Logger::FIELD_LEVEL, $this->getLoggingLevelFromVerbosity());
        $fields    = \array_keys($this->kernelOptions);
        $porcelain = \array_key_exists(Lumberjack\Logger::FIELD_PORCELAIN, $this->kernelOptions);
        $logger    = new Lumberjack\Logger($outStream, null, $level, $fields, $porcelain);
        $this->addRavenHandler($logger);

        return $logger;
    }


    /**
     * Detects whether the App should be instructed to write its output to a file or STDOUT
     *
     * @return resource
     * @throws InvalidArgumentException
     */
    private function getOutputStream()
    {
        $outfile = $this->getKernelOpt(self::OPT_OUT_STREAM);
        if (!$outfile) {
            return STDOUT;
        }
        $outStream = \fopen($outfile, 'wb');
        if (!$outStream) {
            throw new InvalidArgumentException("Unable to write output to file: '$outfile'");
        }
        return $outStream;
    }


    /**
     * Sets the PHP error reporting level
     */
    private function setErrorReporting(): void
    {
        \error_reporting((int)$this->getKernelOpt(self::OPT_ERRORS, E_ALL ^ E_NOTICE));
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
        $v = $this->getKernelOpt(self::OPT_VERBOSITY);

        if ($v === null) {
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


    /**
     * @param string[] $appShortOpts
     * @param string[] $appLongOpts
     * @throws InvalidArgumentException
     */
    private function parseCommandLineOptions(array $appShortOpts, array $appLongOpts): void
    {
        // Kernel uses these
        $kernelShortOpts = [self::OPT_OUT_STREAM, self::OPT_ERR_STREAM, self::OPT_VERBOSITY,];
        $kernelLongOpts  = [
            self::OPT_ERRORS,
            Lumberjack\Logger::FIELD_MEM,
            Lumberjack\Logger::FIELD_TRUE_MEM,
            Lumberjack\Logger::FIELD_PID,
            Lumberjack\Logger::FIELD_LEVEL,
            Lumberjack\Logger::FIELD_PORCELAIN,
        ];

        // Ensure app not attempting to use options that are already in use by kernel
        $this->assertValidAppOptions($appShortOpts, $kernelShortOpts, true);
        $this->assertValidAppOptions($appLongOpts, $kernelLongOpts, false);

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
     * Checks that a CLI app is not attempting to use options that are in use by the kernel
     *
     * @param string[] $appOpts
     * @param string[] $kernelOpts
     * @param bool     $isShort Whether we are dealing with 'short' or 'long' options
     * @throws InvalidArgumentException
     */
    private function assertValidAppOptions(array $appOpts, array $kernelOpts, bool $isShort): void
    {
        $type = $isShort ? 'short' : 'long';
        foreach ($kernelOpts as $kernelShortOpt) {
            if (\in_array($kernelShortOpt, $appOpts, true)) {
                throw new InvalidArgumentException("Invalid app $type option collides with kernel $type option: '$kernelShortOpt'");
            }
        }
    }


    /**
     * Returns a kernel option's value, or $default if not defined
     *
     * Right trims any colon chars so we can re-use the constants with required/optional decoration still appended.
     *
     * @param string $name
     * @param null   $default
     * @return mixed
     */
    private function getKernelOpt(string $name, $default = null)
    {
        $trimmedName = \rtrim($name, ':');
        return \array_key_exists($trimmedName, $this->kernelOptions) ? $this->kernelOptions[$trimmedName] : $default;
    }
}
