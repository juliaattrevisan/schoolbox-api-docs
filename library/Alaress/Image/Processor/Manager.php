<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Image\Processor;

use Alaress\Database\Predicate\Builder;
use Alaress\Database\Predicate\InvalidColumnException;
use Alaress\Database\Quoter\Mysql\Quoter;
use Alaress\Image;
use Alaress\Image\Transformation\ITransformFactory;
use Alaress\Lock\InstanceLockFactory;
use InvalidArgumentException;
use Monolog\Logger;
use OutOfBoundsException;
use PDO;
use PDOException;
use Predis\ClientInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\External as SchoolboxExternal;
use Schoolbox\Resource\FilesystemFactory;
use Schoolbox\Resource\Storage\Transform;
use Symfony\Component\Lock\Exception\LockAcquiringException;
use Symfony\Component\Lock\Exception\LockConflictedException;
use Symfony\Component\Lock\Exception as LockException;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\LockInterface;
use Symfony\Component\Lock\Store\RedisStore;
use UnderflowException;
use UnexpectedValueException;

/**
 * The parent process logic used by the background cron job that pre-transforms images
 *
 * Two modes:
 * * 'normal' mode performs a set of standard transforms + retrying timed-out web request transformations
 * * 'retry-failed' mode re-attempts transforms that failed, eg because original has file has been restored
 *
 * Design considerations:
 * * Need to check for overlapping execution using Lock
 * * We cannot use our DB abstraction because we cannot have open DB connections when forking children.
 *   When one worker finishes, the connection is terminated for other workers.
 *   Our DB abstraction does not allow us to close the underlying connection.
 * * Spawn a single worker to process each transformation. Testing indicates this is the most robust mechanism
 *   to avoid ImageMagick resource depletion issues + temp file usage.
 * * Limit number of concurrent workers
 * @see https://electrictoolbox.com/mysql-connection-php-fork/
 */
class Manager
{
    public const LOCK_NAME = 'image_transform';

    // If a transformation is 'processing' and was last updated greater than this number of seconds ago,
    // assume it has died and attempt another transformation.
    // TODO expose as CLI option
    public const TIMED_OUT_THRESHOLD = 300;

    // List of transformations that we will generate for each image
    private const CUSTOM_TRANSFORMS  = [
        [100, 100],
        [2048, 2048],
    ];
    private const STORAGE_TRANSFORMS = [
        'maxsize',
        'square64',
        'square128',
    ];

    // How long parent will sleep before checking children states again. 5 milli seconds
    private const SPINLOCK_WAIT_TIME_MICRO = 5000;

    // How many seconds should CLI have to process a single transformation
    public const IMAGICK_TIME_LIMIT = 3600;

    // If file is GIF and larger than this, only allow 1 simultaneous execution
    private const MUTEX_FILESIZE_LIMIT_BYTES = 1000000;

    /**  @var int[] */
    private array $workerPids = [];

    /**
     * Mutex containing the process ID of the worker currently processing an animated GIF
     *
     * @var int|null
     */
    private ?int $workerPidMutex = null;

    /**
     * Generate transforms for hashes uploaded during the last N minutes
     *
     * @var int
     */
    private int $timeInterval;

    /**
     * Maximum number of hashes to transform
     *
     * @var int
     */
    private int $imageCountLimit;

    /**
     * Maximum number of concurrent workers
     *
     * @var int
     */
    private int $workerCountLimit;

    private LoggerInterface $logger;


    /**
     * Time at which the manager started. Needed so we can extend max_execution_time each time we launch a worker
     *
     * @var int
     */
    private int $startTime;

    /**
     * Try to repair failed transforms.
     *
     * Eg, original hash was missing, but may have been restored
     *
     * @var bool
     */
    private bool $retryFailed;

    /**
     * Maximum number of times we will attempt each transformation if it throws a catchable exception
     *
     * @var int
     */
    private int $attemptsPerTransform;

    private LockInterface $lock;

    private int $logLevel;

    /**
     * Did processing stop early due to receiving some signal, eg a Ctrl+C or `kill PID`
     *
     * @var bool
     */
    private bool $isInterrupted = false;

    /**
     * This manager process' process ID.
     */
    private int $managerPid;

    /**
     * Redis client, used for checking overlapping execution.
     */
    private ClientInterface $redis;

    /**
     * Local storage directory. Null if using awsS3 storage. No trailing slash.
     *
     * @var string|null
     */
    private ?string $localStorageDir;

    /**
     * Cache of whether a hash's transformation requires a single-process mutex.
     *
     * Enables us to avoid hitting the file-system repeatedly
     *
     * @var bool[]
     */
    private array $requiresMutexCache = [];

    /**
     * @param int             $timeInterval    Time period in minutes to look up images for processing
     * @param int             $imageCountLimit Max number of images to process
     * @param int             $workerCount     Max number of concurrent workers
     * @param LoggerInterface $logger
     * @param bool            $retryFailed
     * @param int             $attemptsPerTransform
     * @param int             $logLevel
     * @param ClientInterface $redis
     */
    public function __construct(
        int $timeInterval,
        int $imageCountLimit,
        int $workerCount,
        LoggerInterface $logger,
        bool $retryFailed,
        int $attemptsPerTransform,
        int $logLevel,
        ClientInterface $redis
    ) {
        $this->timeInterval         = $timeInterval;
        $this->imageCountLimit      = $imageCountLimit;
        $this->workerCountLimit     = min($workerCount, $imageCountLimit);
        $this->logger               = $logger;
        $this->retryFailed          = $retryFailed;
        $this->attemptsPerTransform = $attemptsPerTransform;
        $this->logLevel             = $logLevel;
        $this->managerPid           = posix_getpid();
        $this->redis                = $redis;
    }

    /**
     * @throws OutOfBoundsException
     * @throws InvalidArgumentException
     * @throws RuntimeException
     * @throws SchoolboxExternal\Exception
     * @throws UnexpectedValueException
     * @throws InvalidColumnException
     */
    public function run(): bool
    {
        pcntl_async_signals(true);
        pcntl_signal(SIGINT, [$this, 'signalHandler']);  // eg Ctrl+C
        pcntl_signal(SIGTERM, [$this, 'signalHandler']); // eg `kill PID`
        $this->startTime = time();
        $pdo             = $this->buildPdo();
        $this->lock = $this->checkOverlappingExecution();
        $this->localStorageDir = $this->deriveLocalStorageDir($pdo);
        $transformations = $this->generateTransformations($this->deriveHashList($pdo), $pdo);
        unset($pdo);
        $this->processTransformations($transformations);
        $this->lock->release();
        return $this->isInterrupted;
    }


    /**
     * Returns the local storage dir, or NULL if not using local storage
     *
     * @param PDO $pdo
     * @return string|null
     * @throws PDOException
     */
    private function deriveLocalStorageDir(PDO $pdo): ?string
    {
        if ($this->getConfigValue($pdo, ConfigMapper::NAME_OTFS_ADAPTER) === FilesystemFactory::FILE_SYSTEM_ADAPTER_AWS_S3) {
            return null;
        }
        return rtrim($this->getConfigValue($pdo, 'storage_dir'), DIRECTORY_SEPARATOR);
    }


    /**
     * Returns the specified config value
     *
     * @param PDO    $pdo
     * @param string $configName
     * @return string|null
     * @throws PDOException
     */
    private function getConfigValue(PDO $pdo, string $configName): ?string
    {
        $statement = $pdo->prepare("SELECT value FROM config WHERE name = :name");
        $statement->execute(['name' => $configName]);
        return $statement->fetch(PDO::FETCH_COLUMN);
    }


    /**
     * Signal handler
     *
     *  SIGINT  - delete temp files and exit
     *  SIGKILL - die immediately, don't clean up temp files
     *
     * @param int $signal
     */
    public function signalHandler(int $signal): void
    {
        if (posix_getpid() !== $this->managerPid) {
            // This occurs when child has forked, but not yet replaced the signal handler
            $this->logger->warning(sprintf("[%s] Caught signal $signal, exiting", posix_getpid()));
            exit(1);
        }
        if ($signal === SIGINT) {
            echo "\n";
        }
        $this->isInterrupted = true;
        switch ($signal) {
            case SIGINT:
            case SIGTERM:
                $signalName = $signal === SIGINT ? 'SIGINT' : 'SIGTERM';
                $this->logger->warning($this->prefixLog("Caught $signalName ($signal) signal, sending $signalName to workers"));
                foreach ($this->workerPids as $workerPid) {
                    $this->logger->warning($this->prefixLog("Sending $signalName to worker: $workerPid"));
                    posix_kill($workerPid, $signal);
                }
                break;
            default:
                $this->logger->error($this->prefixLog("Caught unknown signal '$signal'"));
        }
    }


    /**
     * Workers may take up to self::IMAGICK_TIME_LIMIT to process.
     * Each time we launch a worker, we must therefore ensure this parent:
     * 1. Continues to hold the lock, and
     * 2. Stays alive
     *
     * @throws LockAcquiringException
     * @throws LockConflictedException
     * @throws LockException\LockReleasingException
     */
    private function renewLockAndMaxExecutionTime(): void
    {
        // TODO, this should be $lock->refresh(), but that's throwing an exception for some reason?
        $this->lock->release();
        $this->lock->acquire();
        $maxExecutionTime = time() - $this->startTime + self::IMAGICK_TIME_LIMIT;
        ini_set('max_execution_time', (string)$maxExecutionTime);
    }


    /**
     * Builds and returns a PDO database connection
     *
     * @return PDO
     * @throws PDOException
     */
    private function buildPdo(): PDO
    {
        $options = [
            PDO::ATTR_PERSISTENT         => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
        ];
        $pdo     = new PDO(SQL_dsn, SQL_user, SQL_passwd, $options);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec("SET time_zone = (SELECT `value` FROM `config` WHERE name LIKE 'date_timezone')");
        return $pdo;
    }


    /**
     * Acquires a lock to prevent multiple overlapping executions of this manager
     *
     * @return LockInterface
     * @throws LockException\InvalidArgumentException
     * @throws LockException\InvalidTtlException
     */
    private function checkOverlappingExecution(): LockInterface
    {
        $instanceLockFactory = new InstanceLockFactory(new LockFactory(new RedisStore($this->redis)), INSTANCE_ID);
        $timeout             = self::IMAGICK_TIME_LIMIT;
        $lock                = $instanceLockFactory->createLock(self::LOCK_NAME, $timeout);

        try {
            $acquired = $lock->acquire();
        } catch (LockConflictedException | LockAcquiringException $e) {
            $acquired = false;
        }
        if (!$acquired) {
            $this->logger->warning("Cannot acquire lock. Likely previous execution still in progress. Exiting");
            exit;
        }
        return $lock;
    }


    /**
     * Derive the set of images uploaded in the past interval
     *
     * @param PDO $pdo
     * @return string[][]
     * @type  string     $return[]['hash']
     * @type  string     $return[]['ext']
     * @type  int        $return[]['filesize']
     */
    private function deriveHashList(PDO $pdo): array
    {
        if ($this->retryFailed) {
            return [];
        }

        // Retrieve list of images uploaded within the N minutes up to a max of N
        $query = "
            SELECT `hash`, s.ext, s.filesize FROM `storage` s
            JOIN allowedFileExtension afe ON s.ext = afe.extension
            JOIN allowedFileTypes aft
                ON afe.fileTypeID = aft.fileTypeID
                AND aft.mime LIKE 'image%'
                AND aft.mime NOT LIKE 'image%svg%'
                AND aft.mime NOT LIKE 'image%webp%'
                AND aft.mime NOT LIKE 'image%heic%'
            WHERE s.uploaded_on >= (NOW() - INTERVAL {$this->timeInterval} MINUTE)
            ORDER BY s.uploaded_on
            LIMIT 0, {$this->imageCountLimit}";
        return $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);
    }


    /**
     * Generate a set of transformations to execute.
     *
     * Exclude transformations that are already done.
     * Includes transformations that php-fpm timed out on.
     *
     * @param string[][] $hashList
     * @type  string     $hashList[]['hash']
     * @type  string     $hashList[]['ext']
     * @type  int        $hashList[]['filesize']
     * @param PDO      $pdo
     * @return Transformation[]
     * @throws InvalidColumnException
     * @throws OutOfBoundsException
     * @throws UnderflowException
     * @throws PDOException
     */
    private function generateTransformations(array $hashList, PDO $pdo): array
    {
        $myImageTransforms  = new Image\Transformation\MyImage();
        $storageTransforms  = new Image\Transformation\Storage();
        $iconTransforms     = new Image\Transformation\Icon();
        $portraitTransforms = new Image\Transformation\Portrait();
        /** @var ITransformFactory[] $transformFactories */
        $transformFactories = [$myImageTransforms, $storageTransforms, $iconTransforms, $portraitTransforms];

        $existingTransforms = $this->getExistingTransformsPerHash(array_column($hashList, 'hash'), $pdo);

        $transformations = [];
        foreach ($hashList as $row) {
            $hash     = $row['hash'];
            $ext      = $row['ext'];
            $fileSize = (int)$row['filesize'];

            $this->logger->debug($this->prefixLog("Scheduling standard transforms. Hash: '$hash'"));
            // Custom Width x Height transformations
            foreach (self::CUSTOM_TRANSFORMS as $widthAndHeight) {
                [$width, $height] = $widthAndHeight;
                $cacheKey = $myImageTransforms->getCacheKey($width, $height);
                if (array_key_exists($cacheKey, $existingTransforms[$hash] ?? [])) {
                    $state = $existingTransforms[$hash][$cacheKey];
                    $this->logger->debug($this->prefixLog(
                        "Skipping existing transform. Hash: '$hash', CacheKey: '$cacheKey', State: '$state'"
                    ));
                    continue;
                }
                $this->logger->debug($this->prefixLog("Scheduling standard transform. Hash: '$hash', CacheKey: '$cacheKey'"));
                $transform                         = $myImageTransforms->retrieve($width, $height);
                $transformations[$hash][$cacheKey] = new Transformation($hash, $cacheKey, $transform, $ext, $fileSize);
            }
            // Preset transformations
            foreach (self::STORAGE_TRANSFORMS as $size) {
                $cacheKey = $storageTransforms->getCacheKey($size);
                if (array_key_exists($cacheKey, $existingTransforms[$hash] ?? [])) {
                    $state = $existingTransforms[$hash][$cacheKey];
                    $this->logger->debug($this->prefixLog(
                        "Skipping existing transform. Hash: '$hash', CacheKey: '$cacheKey', State: '$state'"
                    ));
                    continue;
                }
                $this->logger->debug($this->prefixLog("Scheduling standard transform. Hash: '$hash', CacheKey: '$cacheKey'"));
                $transform                         = $storageTransforms->retrieve($size);
                $transformations[$hash][$cacheKey] = new Transformation($hash, $cacheKey, $transform, $ext, $fileSize);
            }
        }

        // Insert any transformations that appear to have timed out
        foreach ($this->getTimedOutTransformations($pdo) as $hash => $cacheKeys) {
            foreach ($cacheKeys as $cacheKey) {
                // Don't re-schedule if already scheduled
                if (array_key_exists($cacheKey, $transformations[$hash] ?? [])) {
                    $this->logger->debug($this->prefixLog("Skipping already scheduled transform. Hash: '$hash', CacheKey: '$cacheKey'"));
                    continue;
                }
                // Determine which transform factory generated this cache key
                /** @var ITransformFactory[] $recognised */
                $recognised = [];
                foreach ($transformFactories as $transformFactory) {
                    if ($transformFactory->recognisesCacheKey($cacheKey)) {
                        $recognised[] = $transformFactory;
                    }
                }
                if (!count($recognised)) {
                    $this->logger->error($this->prefixLog("No transform factory recognises cache key. Hash: '$hash', CacheKey: '$cacheKey'"));
                    continue;
                }
                if (count($recognised) > 1) {
                    $callback  = static function (ITransformFactory $factory) {
                        return "'" . get_class($factory) . "'";
                    };
                    $factories = implode(',', array_map($callback, $recognised));
                    $this->logger->error($this->prefixLog("Multiple factories ($factories) recognise cache key: '$cacheKey'"));
                    continue;
                }
                $this->logger->debug($this->prefixLog("Scheduling timed-out transform. Hash: '$hash', CacheKey: '$cacheKey'"));
                $transform                         = $recognised[0]->retrieveForCacheKey($cacheKey);
                $transformations[$hash][$cacheKey] = new Transformation($hash, $cacheKey, $transform, null, null);
            }
        }

        // Flatten nested transformation array
        $flat = [];
        foreach ($transformations as $hashTransformations) {
            foreach ($hashTransformations as $transformation) {
                $flat[] = $transformation;
            }
        }
        return $flat;
    }


    /**
     * Returns transformations that have been processing/failed for longer than N minutes.
     *
     * We can assume these are php-fpm transformations that have timed out because:
     * * php-fpm transformations time out after ~60s and are left in the 'processing' state
     * * cli transformations have overlap protection, so the CLI should never re-transform a
     *   transformation being transformed by another CLI process.
     *
     * We will order by oldest to newest, to protect ourselves from a scenario where the same set of transforms
     * fail and stay in the processing state forever.
     *
     * @param PDO  $pdo
     * @return string[][]
     */
    private function getTimedOutTransformations(PDO $pdo): array
    {
        $state = $this->retryFailed ? Transform\Model::STATE_FAILED : Transform\Model::STATE_PROCESSING;

        $threshold = self::TIMED_OUT_THRESHOLD;
        $sql           = "
            SELECT hash, transform
            FROM storage_transform
            WHERE
                state = '$state' AND
                updated_at < (NOW() - INTERVAL {$threshold} SECOND)
            ORDER BY updated_at
            LIMIT 0, {$this->imageCountLimit}
        ";
        $indexedByHash = [];
        foreach ($pdo->query($sql)->fetchAll(PDO::FETCH_NUM) as [$hash, $cacheKey]) {
            $indexedByHash[$hash][] = $cacheKey;
        }
        return $indexedByHash;
    }


    /**
     * Add suitable prefix to log message to identifier the manager's logs
     *
     * @param string $message
     * @return string
     */
    private function prefixLog(string $message): string
    {
        return "[manager] $message";
    }


    /**
     * Returns the state of existing transforms for the specified hash list, indexed by hash, cacheKey
     *
     * @param string[] $hashList
     * @param PDO   $pdo
     * @return string[][]
     * @throws InvalidColumnException
     * @throws UnderflowException
     * @throws PDOException
     */
    private function getExistingTransformsPerHash(array $hashList, PDO $pdo): array
    {
        if (!count($hashList)) {
            return [];
        }
        $pb = (new Builder(new Quoter(), Transform\Mapper::TABLE_NAME))
            ->in(Transform\Mapper::FIELD_HASH, $hashList);

        $whereClause = implode(' AND ', $pb->getWhere());

        $sql       = "SELECT hash, transform, state FROM storage_transform WHERE {$whereClause}";
        $statement = $pdo->prepare($sql);
        $statement->execute($pb->getParams());
        $existingTransforms        = $statement->fetchAll(PDO::FETCH_NUM);
        $existingTransformsPerHash = [];
        foreach ($existingTransforms as [$hash, $cacheKey, $state]) {
            $existingTransformsPerHash[$hash][$cacheKey] = $state;
        }
        return $existingTransformsPerHash;
    }


    /**
     * Check child workers to see if any are finished/killed.
     *
     * If so, remove them from the worker list
     */
    private function workerCleanup(): void
    {
        // Check if worker processes are still running, if not remove from workerList
        // Wait until a child process exits. Returns 0 if no child available. Returns PID of child process that exits.
        while (pcntl_wait($status, WNOHANG | WUNTRACED) > 0) {
            usleep(self::SPINLOCK_WAIT_TIME_MICRO);
        }
        // Remove any children that have exited
        foreach ($this->workerPids as $idx => $workerPid) {
            if (!posix_kill($workerPid, SIG_DFL)) {
                unset($this->workerPids[$idx]);
                if ($workerPid === $this->workerPidMutex) {
                    $this->workerPidMutex = null;
                }
            }
        }
    }

    /**
     * Process all transformations by launching a worker per transformation
     *
     * @param Transformation[] $transformations
     * @throws InvalidArgumentException
     * @throws LockAcquiringException
     * @throws LockConflictedException
     * @throws LockException\LockReleasingException
     * @throws RuntimeException
     * @throws SchoolboxExternal\Exception
     * @throws UnexpectedValueException
     */
    private function processTransformations(array $transformations): void
    {
        $pid = posix_getpid();
        $this->logger->debug($this->prefixLog("Manager PID: $pid"));

        // While still have work to do
        while (count($transformations) && !$this->isInterrupted) {
            $this->workerCleanup();
            // Are we less than concurrent worker limit?
            if (count($this->workerPids) < $this->workerCountLimit) {
                $t             = reset($transformations);
                $requiresMutex = $this->requiresMutex($t);
                if ($requiresMutex && $this->workerPidMutex !== null) {
                    // Don't allow multiple workers that require a mutex to execute simultaneously
                    sleep(1);
                    continue;
                }
                // Delegate next hash processing to a new worker
                array_shift($transformations);
                $this->renewLockAndMaxExecutionTime();
                $pid = pcntl_fork();
                if ($pid === -1) {
                    // Unable to fork.
                    $this->logger->error($this->prefixLog("Unable to start image processing worker"));
                    exit(1);
                }
                if ($pid) {
                    $this->logger->info($this->prefixLog("Forked worker w/ PID: $pid"));
                    $this->workerPids[] = $pid;
                    if ($requiresMutex) {
                        $this->logger->notice($this->prefixLog("Mutex worker started"));
                        $this->workerPidMutex = $pid;
                    }
                } else {
                    // Worker Process
                    $verbosity = match ($this->logLevel) {
                        Logger::DEBUG  => '-vvv',
                        Logger::INFO   => '-vv',
                        Logger::NOTICE => '-v',
                        default        => '',
                    };
                    $command = sprintf(
                        'php %s %s --hash %s --cache-key %s --transform %s %s --attempts %s',
                        escapeshellarg(SITE_root . '/tools/image/worker.php'),
                        $verbosity,
                        escapeshellarg($t->getHash()),
                        escapeshellarg($t->getCacheKey()),
                        escapeshellarg(base64_encode(serialize($t->getTransform()))),
                        ($this->retryFailed ? '--retry-failed' : ''),
                        escapeshellarg((string)$this->attemptsPerTransform),
                    );
                    passthru($command, $resultCode);
                    exit($resultCode);
                }
            }
            usleep(self::SPINLOCK_WAIT_TIME_MICRO);
        }
        while (count($this->workerPids)) {
            $this->workerCleanup();
            usleep(self::SPINLOCK_WAIT_TIME_MICRO);
        }

        $this->logger->info($this->prefixLog("Finished"));
    }


    /**
     * Returns whether the only a single worker should be allowed to run while transforming the specified hash
     *
     * Will return true when:
     * * storage is local and original file is an animated GIF over 1MB in size, or
     * * storage is aws and original file is a GIF over 1MB in size
     *
     * @param Transformation $t
     * @return bool
     */
    private function requiresMutex(Transformation $t): bool
    {
        $hash = $t->getHash();
        if (!array_key_exists($hash, $this->requiresMutexCache)) {
            $this->logger->debug($this->prefixLog("Checking mutex required: '$hash'"));
            if ($this->localStorageDir !== null) {
                // If using local storage
                $path        = \Schoolbox\Resource\Storage\Manager::getPathFromHash($hash);
                $cmd         = "/usr/bin/identify -format '%n\\n' {$this->localStorageDir}/$path | head -n 1";
                $countLayers = (int)exec($cmd, $output, $exitCode);
                if ($exitCode) {
                    $this->logger->error("Unable to determine image layer count, assuming multiple layers");
                    $this->requiresMutexCache[$hash] = true;
                } else {
                    // Require mutex if layered and >1MB
                    $this->logger->debug($this->prefixLog("Count Layers: $countLayers"));
                    $this->logger->debug($this->prefixLog("FileSize: {$t->getFileSize()}"));
                    $this->requiresMutexCache[$hash] =
                        $countLayers > 1 &&
                        (
                            $t->getFileSize() === null ||
                            $t->getFileSize() > self::MUTEX_FILESIZE_LIMIT_BYTES
                        );
                }
            } else {
                // If awsS3, require mutex if GIF and >1MB
                $this->logger->debug($this->prefixLog("File ext: {$t->getExt()}"));
                $this->logger->debug($this->prefixLog("FileSize: {$t->getFileSize()}"));
                $this->requiresMutexCache[$hash] =
                    ($t->getExt() === null || strtolower($t->getExt()) === 'gif') &&
                    ($t->getFileSize() === null || $t->getFileSize() > self::MUTEX_FILESIZE_LIMIT_BYTES);
            }
        }
        return $this->requiresMutexCache[$hash];
    }
}
