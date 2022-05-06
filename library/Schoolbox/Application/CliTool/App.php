<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Application\CliTool;

use Alaress\Database\Connector\IConnector;
use Alaress\Exception\UnreachableException;
use Alaress\Lock\InstanceLockFactory;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Sentry\EventId;
use Sentry\State\HubInterface;
use Throwable;
use Symfony\Component\Lock\Exception\LockAcquiringException;
use Symfony\Component\Lock\Exception\LockConflictedException;
use Symfony\Component\Lock\LockInterface;
use Psr\Log\LoggerInterface;
use Schoolbox\IConfigLoader;
use UnexpectedValueException;

use function floor;
use function is_numeric;
use function strrpos;
use function substr;

/**
 * An App is a job/task/application that can be run by the CLI Kernel
 */
abstract class App
{
    /**
     * The name of the lock that this job should acquire.
     *
     * @see `acquireLock`
     */
    public const LOCK_NAME = null;

    protected const COLOUR_GREEN = "\033[0;32m";
    protected const COLOUR_RED = "\033[0;31m";
    protected const COLOUR_GREY = "\033[0;37m";
    protected const COLOUR_DARK_GREY = "\033[0;30m";
    protected const COLOUR_YELLOW = "\033[0;33m";
    protected const COLOUR_WHITE = "\033[0m";

    protected const COLOUR_BOLD_GREEN = "\033[0;32m";
    protected const COLOUR_BOLD_RED = "\033[0;31m";
    protected const COLOUR_BOLD_GREY = "\033[0;37m";
    protected const COLOUR_BOLD_YELLOW = "\033[0;33m";

    /** @var array */
    private $appOptions;

    /** @var ContainerInterface */
    private $di;

    /** @var resource */
    private $outStream;

    /** @var LoggerInterface */
    private $logger;

    /**
     * Whether this App should persist changes, as opposed to just simulating them
     *
     * @var bool
     */
    private $perform;

    /** @var IConfigLoader */
    private $config;

    /**
     * @var IConnector
     */
    private $databaseInternal;

    /**
     * Returns the short options recognised by this App
     *
     * Will be validated by the kernel at runtime.
     *
     * @return string[]
     */
    public function getShortOpts(): array
    {
        return [];
    }

    /**
     * Returns the long options recognised by this App
     *
     * Will be validated by the kernel at runtime.
     *
     * @return string[]
     */
    public function getLongOpts(): array
    {
        return [];
    }


    /**
     * Returns whether this app should be run as the system root user.
     *
     * * TRUE  => *must* be run as root
     * * FALSE => *should not* be run as root, will log a warning if invoked as root
     * * NULL  => not-applicable, can be run as either root or non-root,
     *
     * Normal tools (especially if they write files) should return FALSE.
     *
     * While normal tools should not be run as root, they are may be invoked by update scripts which
     * are running as root. In this case any files, logs etc will be root owned, however the
     * deployment architecture has a post-deployment script that iterates over data directories and
     * attempts to restore correct ownership.
     *
     * @see debian/postinst (it contains a number of chown calls)
     *
     * @return bool|null
     */
    public function runAsRoot(): ?bool
    {
        // To err on the side of safety, default to FALSE, as otherwise any logs and files written will be
        // owned by root and difficult to clean up or use by other tools or the web server.
        return false;
    }


    /**
     * Common app setup implementation
     *
     * @param array              $appOptions
     * @param ContainerInterface $di
     * @param resource           $outStream
     * @param LoggerInterface    $logger
     * @param bool               $perform
     * @return $this
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function setup(array $appOptions, ContainerInterface $di, $outStream, LoggerInterface $logger, bool $perform): self
    {
        $this->appOptions       = $appOptions;
        $this->di               = $di;
        $this->outStream        = $outStream;
        $this->logger           = $logger;
        $this->perform          = $perform;
        $this->config           = $di->get('config');
        $this->databaseInternal = $di->get('databaseInternal');

        return $this;
    }


    /**
     * If this App must be run as a specific user, then this should return that user's name.
     *
     * Returns NULL if it does not matter.
     *
     * This is critical for scripts that write to local storage, and where we need to ensure:
     * 1. We can actually write the files, and
     * 2. The files and any parent directories have the correct ownership
     *
     * @return string|null
     */
    public function getRequiredProcessUser(): ?string
    {
        return null;
    }


    /**
     * Hands off execution over to the App
     */
    abstract public function run(): void;


    /**
     * Convenience method for setting max execution time of this app.
     *
     * @param float|null $timeout The amount of time, in seconds, this app may
     *                            run. (0: unlimited, null: no time set at all.)
     */
    protected function setMaxExecutionTime(?float $timeout): void
    {
        if (isset($timeout)) {
            ini_set('max_execution_time', (string)$timeout);
        }
    }


    /**
     * Acquires and returns a lock object.
     *
     * @param string     $lockName The name of the lock to acquire.
     *                             Will be automatically made unique across
     *                             separate instances.
     * @param float|null $timeout  The amount of time (in seconds) to hold the
     *                             lock for.
     * @return LockInterface
     *
     * @noinspection PhpDocMissingThrowsInspection
     * @throws \RuntimeException
     */
    protected function acquireLock(string $lockName, ?float $timeout): LockInterface
    {
        /** @noinspection PhpUnhandledExceptionInspection */
        /** @var InstanceLockFactory $lockFactory */
        $lockFactory = $this->getDi()->get('lockFactory');
        $lock        = $lockFactory->createLock($lockName, $timeout);

        // Try to acquire this lock
        try {
            $acquired = $lock->acquire();
        } catch (\Exception $e) {
            /* Any weird exception being thrown here - lock-related or otherwise -
             * is going to preclude a proper lock acquisition and you should be
             * prevented from doing whatever you're doing
             */
            $message = 'This process is already running for this instance: trusting it will self-terminate on timeout';

            $this->logger->warning($message);

            throw new \RuntimeException($message);
        }

        /* You didn't die, but you also couldn't acquire a lock
         * Drop a nice message because this is an intended behaviour
         */
        if (!$acquired) {
            $message = sprintf(
                'Could not acquire %s lock',
                $lockName
            );

            $this->logger->warning($message);

            throw new \RuntimeException($message);
        }

        return $lock;
    }


    /**
     * An alias to refresh a lock but captures the exception thrown
     * when a lock expires
     *
     * @param LockInterface $lock
     * @return void
     * @throws \RuntimeException
     */
    protected function refreshLock(LockInterface $lock): void
    {
        try {
            $lock->refresh();
        } catch (LockConflictedException | LockAcquiringException $e) {
            $message = 'Could not refresh lock; perhaps expired';

            $this->logger->warning($message);

            throw new \RuntimeException($message);
        }
    }


    /**
     * Helper to fetch a CLI option value.
     *
     * Supports options that are still have colon chars suffixed to indicate required/optional status
     * eg 'c', 'c:', 'c::' will all resolve to 'c'
     *
     * If option DOES accept values:
     *     returns that value, or NULL if not specified
     * If option does NOT accept values:
     *     returns false if not specified, true if specified once, or array if specified multiple times
     *
     * @param string $name Must have colon chars still suffixed for proper handling
     * @return mixed
     */
    protected function getOpt(string $name)
    {
        // Does this option accept values?
        if (\preg_match('/:$/', $name)) {
            // return the value
            return $this->appOptions[\rtrim($name, ':')] ?? null;
        }

        // Does not accept values, and not specified
        if (!\array_key_exists($name, $this->appOptions)) {
            return false;
        }

        // Does not accept values, but specified at least once.
        return \is_array($this->appOptions[$name]) ? $this->appOptions[$name] : true;
    }


    /**
     * Returns the command line option's value as an integer, or null if not specified.
     *
     * @param string   $name
     * @param int|null $default
     * @return int|null
     * @throws UnexpectedValueException If option was provided but cannot be returned as an int
     */
    protected function getOptAsInt(string $name, ?int $default = null): ?int
    {
        $value = $this->getOptAsFloat($name);
        if ($value === null) {
            return $default;
        }
        if (($value - floor($value)) !== 0.0) {
            throw new UnexpectedValueException(sprintf("Command line option '$name' is not a whole number. Found: '$value' (%s)", gettype($value)));
        }
        return (int)$value;
    }


    /**
     * Returns the command line option's value as a float, or null if not specified.
     *
     * @param string     $name
     * @param float|null $default
     * @return float|null
     * @throws UnexpectedValueException If option was provided but cannot be returned as an float
     */
    protected function getOptAsFloat(string $name, ?float $default = null): ?float
    {
        $value = $this->getOpt($name);
        if ($value === null) {
            return $default;
        }
        if (!is_numeric($value)) {
            throw new UnexpectedValueException(sprintf("Command line option '$name' is not numeric. Found: '$value' (%s)", gettype($value)));
        }
        return (float)$value;
    }


    /**
     * Returns the filename of of the script that invoked this App, suitable for generating help output.
     *
     * The following should all return "./myTool.php"
     * * "./relative/path/to/myTool.php"
     * * "relative/path/to/myTool.php"
     * * "./../relative/path/to/myTool.php"
     * * "/absolute/path/to/myTool.php"
     *
     * @return string
     */
    public function getToolFilename(): string
    {
        $scriptName = $_SERVER['SCRIPT_NAME'];
        $lastIndex  = strrpos($scriptName, DIRECTORY_SEPARATOR);
        if ($lastIndex !== false) {
            $scriptName = substr($scriptName, $lastIndex + 1);
        }
        return "./$scriptName";
    }

    /**
     * Returns absolute path to executed script
     *
     * @return string
     */
    public function getToolFilenameAbsolute(): string
    {
        $workingDirectory = $_SERVER['PWD'];
        $scriptName       = $_SERVER['SCRIPT_NAME'];
        return realpath(strpos($scriptName, '/') === 0 ? $scriptName : "$workingDirectory/$scriptName");
    }

    /**
     * Returns any CLI arguments passed to this script
     *
     * @return string[]
     */
    public function getToolArguments(): array
    {
        $args = $_SERVER['argv'];
        array_shift($args);
        return $args;
    }


    /**
     * @return ContainerInterface
     */
    protected function getDi(): ContainerInterface
    {
        return $this->di;
    }


    /**
     * @return resource
     */
    protected function getOutStream()
    {
        return $this->outStream;
    }


    /**
     * @return LoggerInterface
     */
    protected function getLogger(): LoggerInterface
    {
        return $this->logger;
    }


    /**
     * Returns true if user provided -p on CLI or forcePerform has been specified by app
     *
     * @return bool
     */
    protected function perform(): bool
    {
        return $this->perform;
    }


    /**
     * @return IConfigLoader
     */
    protected function getConfig(): IConfigLoader
    {
        return $this->config;
    }


    /**
     * @return IConnector
     */
    protected function getDatabaseInternal(): IConnector
    {
        return $this->databaseInternal;
    }


    /**
     * Is the current CLI session interactive? (In other words, does it accept
     * input from STDIN?)
     *
     * @return bool
     */
    protected function isInteractive(): bool
    {
        return \posix_isatty(STDIN);
    }


    /**
     * Reads a line of input from STDIN, prompting on STDERR
     *
     * @param string $prompt
     * @return string
     */
    protected function readLine(string $prompt): string
    {
        \fwrite(STDERR, $prompt);
        return \readline();
    }


    /**
     * Reads a line of input from STDIN, prompting on STDERR
     *
     * If STDIN is a TTY, then suppresses echo
     *
     * @see https://stackoverflow.com/questions/187736/command-line-password-prompt-in-php
     * @param string $prompt
     * @return string
     */
    protected function readPassword(string $prompt): string
    {
        $isInteractive = $this->isInteractive();
        \fwrite(STDERR, $prompt);
        if ($isInteractive) {
            \system('stty -echo');
        }
        $password = \trim(\fgets(STDIN));
        if ($isInteractive) {
            \system('stty echo');
        }
        // add a new line since the users CR didn't echo
        \fwrite(STDERR, "\n");
        return $password;
    }

    /**
     * Captures an exception event and sends it to Sentry.
     *
     * Returns the event ID object if captured, or null otherwise
     *
     * @param Throwable $e
     * @return EventId|null
     */
    protected function captureException(Throwable $e): ?EventId
    {
        if (!$this->di->has('sentry')) {
            return null;
        }

        try {
            /** @var HubInterface $sentry */
            $sentry = $this->di->get('sentry');
        } catch (NotFoundExceptionInterface | ContainerExceptionInterface $e) {
            // It should not be possible to generate this exception because we checked DI has Sentry a few lines above
            throw new UnreachableException($e);
        }
        return $sentry->captureException($e);
    }


    /**
     * Prompts user to select an option from a list of choices, returns index of their choice
     *
     * @param string $prompt
     * @param array  $choices
     * @return int
     */
    protected function getChoice(string $prompt, array $choices): int
    {
        echo "$prompt\n";
        foreach ($choices as $idx => $choice) {
            echo "$idx. $choice\n";
        }
        while (true) {
            $input = readline("Choice: ");
            if (array_key_exists($input, $choices)) {
                return (int)$input;
            }
            echo "Invalid choice '$input', please try again\n";
        }
    }
}
