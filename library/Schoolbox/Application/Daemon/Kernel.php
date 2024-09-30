<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Application\Daemon;

use Alaress\Async\Stomp\Implementation\Stream as Stomp;
use Alaress\Database\Factory as BaseDatabaseFactory;
use Alaress\Exception\IO\IOException;
use Alaress\Lumberjack\Logger;
use Alaress\Redis\Async\Client as AsyncRedisClient;
use Aura\Di\Container;
use ErrorException;
use Exception;
use PDOException;
use Psr\Log\LoggerInterface;
use RangeException;
use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use RuntimeException;
use Schoolbox\Application\CliKernelAbstract;
use Schoolbox\Application\WebRequest\ShutdownHandler;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Config\DataSource;
use Schoolbox\IConfigLoader;
use Schoolbox\MultiInstance;
use Schoolbox\Notif\MQ\Connector;
use Schoolbox\Notif\MQ\ConnectorFactory;
use Stomp\Protocol\Protocol;
use Stomp\Protocol\Version;
use Stomp\Transport\Parser;

class Kernel extends CliKernelAbstract
{
    // Options keys for $options passed to this Kernel's constructor
    public const OPTION_APP_SHORT_OPTS = 'appShortOpts'; // short options used by the app
    public const OPTION_APP_LONG_OPTS  = 'appLongOpts';  // long options used by the app

    /* System resources available in $this->getSoftLimit() and $this->getHardLimit()
     * @see posix_getrlimit()
     */
    public const RLIMIT_CORE_FILESIZE = 'core';
    public const RLIMIT_CPU_TIME = 'cpu';
    public const RLIMIT_DATA_SEGMENT = 'data';
    public const RLIMIT_FILESIZE = 'filesize';
    public const RLIMIT_FILE_DESCRIPTORS = 'openfiles';
    public const RLIMIT_PROCESSES = 'maxproc';
    public const RLIMIT_MEMORY_LOCK = 'memlock';
    public const RLIMIT_RESIDENT_SET = 'rss';
    public const RLIMIT_STACK_SIZE = 'stack';
    public const RLIMIT_TOTAL_MEMORY = 'totalmem';

    /** @var string */
    private $name;

    /** @var LoopInterface */
    private $loop;

    /**
     * All command line options for this Daemon kernel, logger, and app.
     *
     * Used for:
     * 1. Controlling logging behaviour
     * 2. Passed back into the run() function for function/App-specific config
     *
     * @var array
     */
    private $opts;


    /**
     * Various process limits imposed by the operating system
     *
     * Will be NULL if unlimited
     *
     * @see self::RLIMIT_* constants above
     * @var string[][]|null[][]
     */
    private $limits;


    /**
     * Initialise
     *
     * @param string $name    The name of this Daemon
     * @param array  $options Keys are one of the self::OPTION_X constants
     * @throws RuntimeException
     * @throws \Aura\Di\Exception
     * @throws \Aura\Di\Exception\ContainerLocked
     * @throws \Psr\Container\NotFoundExceptionInterface|\Psr\Container\ContainerExceptionInterface
     * @throws \Aura\Di\Exception\ServiceNotObject
     * @throws \InvalidArgumentException
     * @throws \Schoolbox\External\Exception
     */
    public function __construct(string $name, array $options = [], $bypassMaintenanceModeCheck = false)
    {
        $this->validateOptions($options);
        $this->parseCommandLineOpts($options);

        parent::__construct($options[self::OPTION_LEVEL] ?? null, $bypassMaintenanceModeCheck);

        pcntl_signal(SIGCHLD, SIG_IGN);
        error_reporting($options[self::OPTION_ERRORS] ?? E_ALL ^ E_NOTICE);
        $this->name = $name;

        $this->loop = $this->di->get('react.loop');

        foreach (posix_getrlimit() as $key => $value) {
            [$strength, $attr] = explode(' ', $key);
            $this->limits[$attr][$strength] = $value === 'unlimited' ? null : $value;
        }
    }

    /**
     * Daemon-specific shutdown handling: stop the React loop manually.
     *
     * The loop has been added to the DI container and thus never goes out of
     * scope: this means its destructor is never called, which would stop the
     * loop.
     */
    public function registerShutdownHandling(): void
    {
        parent::registerShutdownHandling();
        $this->getShutdownHandler()->registerFunction(Loop::stop(...), ShutdownHandler::PRIORITY_HIGHEST);
    }

    /**
     * @inheritdoc
     */
    protected function getValidConstructionOptions(): array
    {
        return \array_merge(
            parent::getValidConstructionOptions(),
            [self::OPTION_APP_SHORT_OPTS, self::OPTION_APP_LONG_OPTS]
        );
    }


    /**
     * Processes command line options and any kernel-invocation overrides
     *
     * @param array $options
     */
    private function parseCommandLineOpts(array $options): void
    {
        // Get all Kernel, App, and Logger options from command line
        $appShortOpts = $options[self::OPTION_APP_SHORT_OPTS] ?? '';
        $appLongOpts  = $options[self::OPTION_APP_LONG_OPTS] ?? [];

        $shortOpts  = $appShortOpts . \implode('', $this->getKernelShortOpts());
        $longOpts   = \array_merge($appLongOpts, $this->getKernelLongOpts());
        $this->opts = \getopt($shortOpts, $longOpts);
    }


    /**
     * @param string $name
     * @return mixed
     */
    private function getOpt(string $name)
    {
        return $this->opts[$name] ?? null;
    }


    /**
     * @param string $attribute
     * @return string|null
     * @throws \InvalidArgumentException If passed an unknown process attribute
     * @throws \UnexpectedValueException If no hard limit known for process attribute
     */
    public function getHardLimit(string $attribute): ?string
    {
        if (!\array_key_exists($attribute, $this->limits)) {
            throw new \InvalidArgumentException("Unknown process attribute: '{$attribute}'");
        }
        if (!\array_key_exists('hard', $this->limits[$attribute])) {
            throw new \UnexpectedValueException("No known 'hard' limit for: '{$attribute}'");
        }
        return $this->limits[$attribute]['hard'];
    }


    /**
     * @param string $attribute
     * @return string|null
     * @throws \InvalidArgumentException If passed an unknown process attribute
     * @throws \UnexpectedValueException If no soft limit known for process attribute
     */
    public function getSoftLimit(string $attribute): ?string
    {
        if (!\array_key_exists($attribute, $this->limits)) {
            throw new \InvalidArgumentException("Unknown process attribute: '{$attribute}'");
        }
        if (!\array_key_exists('soft', $this->limits[$attribute])) {
            throw new \UnexpectedValueException("No known 'soft' limit for: '{$attribute}'");
        }
        return $this->limits[$attribute]['soft'];
    }


    /**
     * Go. Do stuff.
     *
     * @param callable $callable
     * @throws Exception
     * @throws \Schoolbox\External\Exception
     */
    public function run($callable = null): void
    {
        if (posix_isatty(STDOUT)) {
            echo <<<EOL

 ||
 || Schoolbox {$this->name} Daemon
 ||


EOL;
        }

        // not much point going on if we've got nothing to do
        if (!is_callable($callable)) {
            $this->logger()->critical("No daemon to launch, aborting");
            exit(self::EXIT_STATUS_KERNEL_ERROR);
        }

        try {
            global $argv;
            $flags = \implode(' ', \array_slice($argv, 1));
            $callable($this->di, $this->loop, $this->logger(), $this->opts, $flags);
        } catch (\Schoolbox\External\Exception $e) {
            $this->logger()->emergency($e->getMessage(), ['exception' => $e]);
            throw $e;
        } catch (Exception $e) {
            $this->logger()->critical(
                "{$this->name} daemon failed to launch: " . $e->getMessage(),
                ['exception' => $e]
            );
            throw $e;
        }

        try {
            $this->loop->run();
            $this->logger()->debug($this->name . " daemon loop ended; preparing to terminate");
        } catch (PDOException $e) {
            $this->logger()->emergency($e->getMessage(), ['exception' => $e]);
            exit(self::EXIT_STATUS_APP_ERROR);
        } catch (\Schoolbox\External\Exception $e) {
            $this->logger()->emergency($e->getMessage(), ['exception' => $e]);
            exit(self::EXIT_STATUS_APP_ERROR);
        } catch (Exception $e) {
            $this->logger()->critical(
                sprintf(
                    "%s daemon received %s: %s",
                    $this->name,
                    get_class($e),
                    $e->getMessage()
                ),
                ['exception' => $e]
            );
            exit(self::EXIT_STATUS_APP_ERROR);
        }
    }


    /**
     * Handle specific kinds of warnings differently
     *
     * @param int    $errNum
     * @param string $errStr
     * @param string $errFile
     * @param int    $errLine
     * @throws ErrorException
     * @throws IOException
     * @throws PDOException
     * @throws RangeException
     * @throws RuntimeException
     */
    protected function onWarning($errNum, $errStr, $errFile, $errLine): void
    {
        if (strpos($errStr, 'stream_select') === 0) {
            // this is fine to be let through
            return;
        }
        if (strpos($errStr, 'unable to shutdown socket') === 0) {
            // this is fine to be let through
            return;
        }
        if (strpos($errStr, 'unable to recvfrom') === 0) {
            // this is fine to be let through
            return;
        }
        if (strpos($errStr, 'unable to select') === 0) {
            // this is fine to be let through
            return;
        }
        if (strpos($errStr, 'fread') === 0) {
            // this is fine to be let through
            return;
        }
        if (strpos($errStr, 'LaravelDate creation raised') === 0) {
            // iiiiignore.
            return;
        }
        if (strpos($errStr, 'gzuncompress(): data error') === 0) {
            // ignore this too;
            // it's a symptom of a compressed string I can do nothing with
            return;
        }
        if (strpos($errStr, 'Undefined index') === 0) {
            $this->logger()->error("$errStr in $errFile:$errLine");
            return;
        }
        if (strpos($errStr, 'Undefined variable') === 0) {
            $this->logger()->error("$errStr in $errFile:$errLine");
            return;
        }
        if (strpos($errStr, 'fwrite') === 0) {
            throw new IOException($errStr, $errNum);
        }
        if (strpos($errStr, 'unserialize') === 0) {
            // throw a specific kind of exception
            throw new RuntimeException($errStr, $errNum);
        }
        if (strpos($errStr, 'Class __PHP_Incomplete_Class') === 0) {
            // throw a specific kind of exception
            throw new RuntimeException($errStr, $errNum);
        }
        if (strpos($errStr, 'fclose') === 0) {
            throw new RangeException($errStr, $errNum);
        }
        if (strpos($errStr, 'pclose') === 0) {
            throw new RangeException($errStr, $errNum);
        }
        if (strpos($errStr, 'Error while sending QUERY packet') === 0) {
            throw new PDOException($errStr);
        }

        try {
            $this->logger()->info("$errStr in $errFile:$errLine [$errNum]", ['char' => 'e']);
        } catch (IOException $e) {
            // it's possible that the warning was from the STDOUT pipe having been closed,
            // in which case attempting to log is only going to exacerbate the issue
        }

        throw new ErrorException($errStr, 0, $errNum, $errFile, $errLine);
    }


    /**
     * @return LoggerInterface
     * @throws \Psr\Container\NotFoundExceptionInterface|\Psr\Container\ContainerExceptionInterface
     */
    protected function createDefaultLogger(): LoggerInterface
    {
        $level = $this->getLoggingLevel($this->getOpt(self::OPT_SHORT_VERBOSITY));

        // Derive the list of fields to prepend to log items
        $fields = Logger::getAvailableFields();
        foreach ($fields as $key => $field) {
            if (!isset($this->opts[$field])) {
                unset($fields[$key]);
            }
        }

        $porcelain = \array_key_exists(self::OPT_LONG_PORCELAIN, $this->opts);
        $logger    = new Logger(STDOUT, STDERR, $level, $fields, $porcelain);

        $this->addSentryHandler($logger);

        return $logger;
    }


    /**
     * Install any internal dependencies into the kernel (routing, etc.)
     *
     * @param Container $di
     * @return Container
     * @throws RuntimeException
     * @throws \Aura\Di\Exception
     * @throws \Aura\Di\Exception\ContainerLocked
     * @throws \Psr\Container\NotFoundExceptionInterface|\Psr\Container\ContainerExceptionInterface
     * @throws \Aura\Di\Exception\ServiceNotObject
     * @throws \InvalidArgumentException
     */
    public static function installDependencies(Container $di): Container
    {
        parent::installDependencies($di);

        // Override AMQP connection with one with a heartbeat
        $di->set(
            'amqp',
            $di->lazy(
                function () use ($di) {
                    /** @var IConfigLoader $conf */
                    $conf = $di->get('config');
                    return (new ConnectorFactory($conf))->build(
                        [],
                        [Connector::AMQP_CONNECTION_OPTION_HEARTBEAT => 15,],
                    );
                }
            ),
        );

        $di->set('stomp', $di->lazy(function () use ($di) {
            /** @var IConfigLoader $conf */
            $conf = $di->get('config');
            $conn = new Stomp\Connection(
                $di->get('react.loop'),
                new Protocol(null, Version::VERSION_1_1),
                new Parser(),
                $conf->get('amqp_host'),
                (int)$conf->get('amqp_stomp_port'),
                $conf->get('amqp_user'),
                $conf->get('amqp_pass'),
                $conf->get('amqp_vhost')
            );
            return new Stomp\Client($conn);
        }));

        // Redis queue subscription (not persistent)
        $di->set('redis.signalQueue.sub', $di->lazy(function () use ($di) {
            /** @var IConfigLoader $conf */
            $conf = $di->get('config');
            return new AsyncRedisClient($conf->get(ConfigMapper::NAME_REDIS_EPHEMERAL_URL), $di->get('react.loop'));
        }));

        // Multi-instance repositories
        $di->set(
            'multiInstance.instanceRepository',
            $di->lazy(fn () => new MultiInstance\InstanceRepository(
                parse_ini_file("/etc/schoolbox/instances.ini", true)
            ))
        );
        $di->set(
            'multiInstance.connectorRepository',
            $di->lazyNew(
                MultiInstance\ConnectorRepository::class,
                [
                    'instanceRepository' => $di->lazyGet('multiInstance.instanceRepository'),
                    'databaseFactory'    => $di->lazyNew(BaseDatabaseFactory::class),
                ],
            )
        );
        $di->set(
            'multiInstance.configRepository',
            $di->lazyNew(
                MultiInstance\ConfigRepository::class,
                [
                    'connectorRepository' => $di->lazyGet('multiInstance.connectorRepository'),
                    'dataSource'          => $di->lazyNew(DataSource::class),
                ],
            )
        );
        $di->set(
            'multiInstance.redisStorageClientRepository',
            $di->lazyNew(
                MultiInstance\RedisClientRepository::class,
                [
                    'configRepository' => $di->lazyGet('multiInstance.configRepository'),
                    'configKey'        => ConfigMapper::NAME_REDIS_URL,
                ],
            )
        );
        $di->set(
            'multiInstance.redisAsyncClientRepository',
            $di->lazyNew(
                MultiInstance\RedisAsyncClientRepository::class,
                [
                    'configRepository' => $di->lazyGet('multiInstance.configRepository'),
                    'configKey'        => ConfigMapper::NAME_REDIS_EPHEMERAL_URL,
                    'loop'             => $di->lazyGet('react.loop'),
                ],
            )
        );
        $di->set('react.loop', $di->lazy(Loop::get(...)));
        return $di;
    }
}
