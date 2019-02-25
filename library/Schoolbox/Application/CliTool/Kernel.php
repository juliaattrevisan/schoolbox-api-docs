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
    private const OPT_ERR_STREAM = 'l:'; // CLI option to allow logs to be written to a file, rather than STDERR
    private const OPT_VERBOSITY  = 'v';  // CLI option that verbosity, unless overridden by Lumberjack\Logger::FIELD_LEVEL

    // These control the kernel and default logger
    private $kernelOptions;

    // These are passed back to the CLI app
    private $appOptions;


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
     */
    public function __construct(array $appShortOpts = [], array $appLongOpts = [])
    {
        parent::__construct();

        $this->parseCommandLineOptions($appShortOpts, $appLongOpts);
        $this->setErrorReporting();
        $this->checkServerApiEnvironment();
    }


    /**
     * Run the app, passing it back any command line options passed into the Kernel's constructor
     *
     * @param App $app
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    public function runApp(App $app): void
    {
        $app->run($this->getDependencyInjector(), $this->logger(), $this->appOptions);
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

        $level     = $this->getKernelOpt(Lumberjack\Logger::FIELD_LEVEL);
        $porcelain = \in_array(Lumberjack\Logger::FIELD_PORCELAIN, $this->kernelOptions, true);
        $logger    = new Lumberjack\Logger($outStream, null, $level, $this->kernelOptions, $porcelain);
        $this->addRavenHandler($logger);

        return $logger;
    }


    /**
     * Sets the PHP error reporting level
     */
    private function setErrorReporting(): void
    {
        \error_reporting($this->getKernelOpt(self::OPT_ERRORS, E_ALL ^ E_NOTICE));
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
        $kernelShortOpts = [self::OPT_ERRORS, self::OPT_ERR_STREAM, self::OPT_VERBOSITY,];
        $kernelLongOpts  = [
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
        $allOpts = \getopt($appShortOpts . $kernelShortOpts, \array_merge($appLongOpts, $kernelLongOpts));

        // Extract the kernel options
        $this->kernelOptions = [];
        foreach (\array_merge($kernelShortOpts, $kernelLongOpts) as $key => $val) {
            if (\array_key_exists(\rtrim($key, ':'), $allOpts)) {
                $this->kernelOptions[$key] = $val;
            }
        }
        // If an exact logging level was not specified, derive one from the verbosity
        if (!\array_key_exists(Lumberjack\Logger::FIELD_LEVEL, $this->kernelOptions)) {
            $this->kernelOptions[Lumberjack\Logger::FIELD_LEVEL] = $this->getLoggingLevelFromVerbosity();
        }

        // Extract the app options
        $this->appOptions = [];
        foreach (\array_merge($appShortOpts, $appLongOpts) as $key => $val) {
            if (\array_key_exists(\rtrim($key, ':'), $allOpts)) {
                $this->appOptions[$key] = $val;
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
