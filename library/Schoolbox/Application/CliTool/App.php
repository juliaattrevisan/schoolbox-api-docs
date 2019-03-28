<?php
/**
 * @copyright   Copyright 2019, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Application\CliTool;

use Aura\Di;
use Psr\Log\LoggerInterface;
use Schoolbox\IConfigLoader;

/**
 * An App is a job/task/application that can be run by the CLI Kernel
 */
abstract class App
{
    protected const COLOUR_GREEN = "\033[0;32m";
    protected const COLOUR_RED = "\033[0;31m";
    protected const COLOUR_GREY = "\033[0;37m";
    protected const COLOUR_YELLOW = "\033[0;33m";
    protected const COLOUR_WHITE = "\033[0m";

    protected const COLOUR_BOLD_GREEN = "\033[0;32m";
    protected const COLOUR_BOLD_RED = "\033[0;31m";
    protected const COLOUR_BOLD_GREY = "\033[0;37m";
    protected const COLOUR_BOLD_YELLOW = "\033[0;33m";

    /** @var array */
    private $appOptions;

    /** @var Di\Container */
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
     * Common app setup implementation
     *
     * @param array           $appOptions
     * @param Di\Container    $di
     * @param resource        $outStream
     * @param LoggerInterface $logger
     * @param bool            $perform
     * @return $this
     * @throws Di\Exception\ServiceNotFound
     */
    public function setup(array $appOptions, Di\Container $di, $outStream, LoggerInterface $logger, bool $perform): self
    {
        $this->appOptions = $appOptions;
        $this->di         = $di;
        $this->outStream  = $outStream;
        $this->logger     = $logger;
        $this->perform    = $perform;
        $this->config     = $di->get('config');

        return $this;
    }


    /**
     * Hands off execution over to the App
     */
    abstract public function run(): void;


    /**
     * Helper to fetch a CLI option value.
     *
     * Supports options that are still have colon chars suffixed to indicate required/optional status
     * eg 'c', 'c:', 'c::' will all resolve to 'c'
     *
     * If option DOES accept values:
     *     returns that value, or NULL if not specified
     * If option does NOT accept values:
     *     returns true if specified once, or array if specified multiple times
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
     * @return Di\Container
     */
    protected function getDi(): Di\Container
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
        $isInteractive = \posix_isatty(STDIN);
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
}
