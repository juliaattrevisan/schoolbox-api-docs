<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Image\Processor;

use Alaress\Image;
use Alaress\Image\Cache\LocalFS;
use Alaress\Image\Exception\ConcurrentTransformationException;
use Alaress\Image\Exception\LayeredNonGifException;
use Alaress\Image\ImagickConfigParser;
use Alaress\Image\ITransform;
use League\Flysystem\FileNotFoundException;
use LogicException;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Schoolbox\Application\CliKernelAbstract;
use Schoolbox\File as SchoolboxFile;
use Schoolbox\Instance;
use Schoolbox\Mapper as SchoolboxMapper;
use Schoolbox\Resource\Storage;
use Schoolbox\Application\CliTool;
use SplFileInfo;
use Throwable;

/**
 * The worker process logic used by the background cron job that pre-transforms images
 *
 * A new worker is spawned for every distinct transformation.
 *
 * Not intended to be called directly by end users: this should be called only
 * by the image processor.
 */
class Worker extends CliTool\App
{
    private const OPT_LONG_HASH         = 'hash:';
    private const OPT_LONG_CACHE_KEY    = 'cache-key:';
    private const OPT_LONG_TRANSFORM    = 'transform:';
    private const OPT_LONG_RETRY_FAILED = 'retry-failed';
    private const OPT_LONG_ATTEMPTS     = 'attempts';

    /**
     * The original file hash
     *
     * @var string
     */
    private string $hash;

    /**
     * A string that describes/represents the transformation being applied
     *
     * @var string
     */
    private string $cacheKey;

    /**
     * Transform to apply to original image
     *
     * @var ITransform
     */
    private ITransform $transform;

    /**
     * After how many seconds should we assume that a transformation still 'processing' has timed out and can
     * be re-attempted
     *
     * @var int
     */
    private int $timeOutThreshold;

    /**
     * How long should image magick be allowed to execute for.
     *
     * This must be less than or equal to the Lock/max-execution-time of the PHP process
     *
     * @var int
     */
    private int $imageMagickTimeout;

    /**
     * Normally we want to notify Sentry of failures. However if re-trying previous failures, we want to suppress
     * that behaviour as it is quite normal for failed transforms to not be fixable on subsequent attempts.
     *
     * @var bool
     */
    private bool $suppressSentryReporting;

    /**
     * Maximum number of times we will attempt each transformation if it throws a catchable exception
     *
     * @var int
     */
    private int $attemptsPerTransform;

    /**
     * @param string     $hash
     * @param string     $cacheKey
     * @param ITransform $transform
     * @param int        $timeOutThreshold
     * @param int        $imageMagickTimeout
     * @param bool       $suppressSentryReporting
     * @param int        $attemptsPerTransform
     */
    public function __construct(
        string $hash,
        string $cacheKey,
        ITransform $transform,
        int $timeOutThreshold,
        int $imageMagickTimeout,
        bool $suppressSentryReporting,
        int $attemptsPerTransform
    ) {
        $this->hash                    = $hash;
        $this->cacheKey                = $cacheKey;
        $this->transform               = $transform;
        $this->timeOutThreshold        = $timeOutThreshold;
        $this->imageMagickTimeout      = $imageMagickTimeout;
        $this->suppressSentryReporting = $suppressSentryReporting;
        $this->attemptsPerTransform    = $attemptsPerTransform;
    }

    /** @inheritDoc */
    public function getRequiredProcessUser(): ?string
    {
        return 'www-data';
    }

    /**
     * @inheritdoc
     */
    public function getLongOpts(): array
    {
        return [
            self::OPT_LONG_HASH,
            self::OPT_LONG_CACHE_KEY,
            self::OPT_LONG_TRANSFORM,
            self::OPT_LONG_RETRY_FAILED,
            self::OPT_LONG_ATTEMPTS,
        ];
    }

    /** @inheritDoc */
    public function setup(array $appOptions, ContainerInterface $di, $outStream, LoggerInterface $logger, bool $perform): CliTool\App
    {
        parent::setup($appOptions, $di, $outStream, $logger, $perform);
        pcntl_signal(SIGINT, [$this, 'signalHandler']);   // eg Ctrl+C
        pcntl_signal(SIGTERM, SIG_DFL);                   // eg `kill PID`
        return $this;
    }


    /**
     * Signal handling
     *
     * @param int $signal
     */
    public function signalHandler(int $signal): void
    {
        $this->getLogger()->warning($this->prefixLog("Caught signal '$signal'"));
        if ($signal === SIGINT) {
            $resourceLimitMap = (new ImagickConfigParser())->parseImageMagickResourceLimits();
            $tempPath         = rtrim($resourceLimitMap['temporary-path'], DIRECTORY_SEPARATOR);
            $this->getLogger()->warning($this->prefixLog("Deleting temp files then exiting"));
            $match = "$tempPath/magick-";
            $this->getLogger()->debug($this->prefixLog("Deleting open files that match: '$match'"));
            $pid = posix_getpid();
            exec("lsof -Fn -p $pid 2>/dev/null | grep '$match' | grep -E '^n/' | sed s/^n//g", $output);
            foreach ($output as $line) {
                $this->getLogger()->debug($this->prefixLog("Deleting temp file: '$line'"));
                unlink($line);
            }
        } else {
            $this->getLogger()->error($this->prefixLog("Unrecognised signal '$signal'"));
        }

        exit(1);
    }


    /**
     * @throws LogicException
     * @throws SchoolboxFile\Exception
     * @throws SchoolboxMapper\Exception
     */
    public function run(): void
    {
        $hash = $this->getOpt(self::OPT_LONG_HASH);
        if (!isset($hash)) {
            $this->getLogger()->error('A hash must be provided');
            exit(CliKernelAbstract::EXIT_STATUS_APP_ERROR);
        }
        $cacheKey = $this->getOpt(self::OPT_LONG_CACHE_KEY);
        if (!isset($cacheKey)) {
            $this->getLogger()->error('A cache-key must be provided');
            exit(CliKernelAbstract::EXIT_STATUS_APP_ERROR);
        }
        $transform = $this->getOpt(self::OPT_LONG_TRANSFORM);
        if (!isset($transform)) {
            $this->getLogger()->error('A transform must be provided');
            exit(CliKernelAbstract::EXIT_STATUS_APP_ERROR);
        }
        $transform = unserialize(base64_decode($transform));
        if (!$transform instanceof ITransform) {
            $this->getLogger()->error('A transform must be provided, and a base64-encoded serialized ITransform instance');
            exit(CliKernelAbstract::EXIT_STATUS_APP_ERROR);
        }
        $attempts = $this->getOptAsInt(self::OPT_LONG_ATTEMPTS);
        if (!isset($attempts) || $attempts <= 0) {
            $this->getLogger()->error('An attempts value must be provided, and be greater than 0');
            exit(CliKernelAbstract::EXIT_STATUS_APP_ERROR);
        }

        $this->getLogger()->debug($this->prefixLog("Processing: Hash: {$hash}, CacheKey: '{$cacheKey}'"));
        $hashObject = $this->fetchHashObject($hash);
        if ($hashObject) {
            $this->generateTransform($hashObject, $cacheKey, $transform, $attempts);
        }
        $this->getLogger()->debug($this->prefixLog("Finished"));
    }


    /**
     * Find the storage object for this hash
     *
     * Normally this is a storage file, but in rare cases may represent a static file.
     *
     * @param string $hash
     * @return Storage\Model|null
     * @throws LogicException
     * @throws SchoolboxFile\Exception
     * @throws SchoolboxMapper\Exception
     */
    private function fetchHashObject(string $hash): ?Storage\Model
    {
        /** @var Storage\Model|null $hashObject */
        $hashObject = Storage\Mapper::getInstance()->findByColumn('hash', $hash)->first();
        if (!$hashObject) {
            // This is most likely the rare case where we failed to transcode a static image.
            // We need to locate that image from the hash.
            $this->getLogger()->warning($this->prefixLog("No storage record found for hash: {$hash}, searching static files..."));

            $itr                = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(WEB_root, RecursiveDirectoryIterator::SKIP_DOTS));
            $excludedExtensions = [
                '1',
                'checksum',
                'css',
                'csv',
                'def',
                'editorconfig',
                'eot',
                'gitattributes',
                'gitignore',
                'htm',
                'html',
                'js',
                'jshintrc',
                'json',
                'jst',
                'less',
                'lock',
                'map',
                'md',
                'npmignore',
                'otf',
                'php',
                'psd',
                'scss',
                'spmignore',
                'ttf',
                'txt',
                'woff',
                'woff2',
                'xml',
                'yml',
            ];
            $hashGenerator      = new Storage\HashGenerator();
            foreach ($itr as $item) {
                /** @var SplFileInfo $item */
                if (!$item->isFile() || in_array($item->getExtension(), $excludedExtensions, true)) {
                    continue;
                }
                $fp       = fopen($item->getPathname(), 'rb');
                $itemHash = $hashGenerator->generateHash($fp);
                fclose($fp);
                if ($itemHash === $hash) {
                    $fileName = str_replace(WEB_root, '', $item->getPathname());
                    $this->getLogger()->notice($this->prefixLog("Static file found: '$fileName'"));
                    $hashObject = LocalFS::getStorageModel(WEB_root, $fileName);
                    break;
                }
            }
        }
        return $hashObject;
    }

    /**
     * Prefix this process's PID to a log message
     *
     * @param string $message
     * @return string
     */
    private function prefixLog(string $message): string
    {
        return sprintf("[%d] $message", posix_getpid());
    }

    /**
     * Apply the specified transform to the storage object and save it using specified cache key
     *
     * @param Storage\Model    $hashObject
     * @param string           $cacheKey
     * @param Image\ITransform $transform
     * @param int              $attempts
     */
    private function generateTransform(Storage\Model $hashObject, string $cacheKey, Image\ITransform $transform, int $attempts): void
    {
        $hash      = $hashObject->getHash();
        $processor = new Image\Processor($hashObject, $cacheKey, $transform, Manager::IMAGICK_TIME_LIMIT);

        try {
            // If transform was previously attempted longer than N seconds ago, assume it failed and retry it.
            //we are in the cli, so perm fail any images that fail here.
            $transformFile = $processor->load($attempts, Manager::TIMED_OUT_THRESHOLD, true);
            if (!$transformFile->isCacheHit()) {
                $this->getLogger()->info($this->prefixLog(sprintf("%s: %s", $hash . '-' . $cacheKey, 'success')));
            } else {
                $this->getLogger()->warning($this->prefixLog(sprintf("%s: %s", $hash . '-' . $cacheKey, 'already cached')));
            }
        } catch (ConcurrentTransformationException $e) {
            // Another process (probably a php-fpm web request) is already processing this transformation, and has not
            // yet timed out. We'll just log it and skip it for now.
            $this->getLogger()->warning($e->getMessage());
        } catch (Throwable $e) {
            // Need to handle here rather than bubbling to kernel because we want to render the process id in the log.
            // Don't send exception to Sentry if:
            // * we are retrying failed transforms, as it's quite normal for these to continue to fail.
            // * we attempted to transcode a non-GIF layered image
            // * this is a staging instance and file not found on disk. This is normal after a prd-to-stg sync
            if (
                !$this->suppressSentryReporting &&
                !($e instanceof LayeredNonGifException) &&
                !($this->getConfig()->get('site_type') === Instance::SITE_TYPE_STAGING && $e instanceof FileNotFoundException)
            ) {
                $this->captureException($e);
            }
            // Print all exceptions in chain
            $this->getLogger()->error($this->prefixLog(sprintf("%s: %s", $hash . '-' . $cacheKey, "Failed")));
            while ($e) {
                $this->getLogger()->error($this->prefixLog($e->getMessage()));
                foreach (explode("\n", $e->getTraceAsString()) as $line) {
                    $this->getLogger()->error($this->prefixLog($line));
                }
                $e = $e->getPrevious();
            }
        }
    }
}
