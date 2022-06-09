<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Alaress\Image;

use Alaress\Image\Exception\ConcurrentTransformationException;
use Alaress\Image\Exception\CorruptImageException;
use Alaress\Image\Exception\LayeredNonGifException;
use Alaress_Byte_Format;
use Alaress_Date_Format;
use DomainException;
use Imagick;
use ImagickException;
use Imagine\Exception as ImagineException;
use Alaress\Image\Transform\RotateOrientation;
use Imagine\Image\ImageInterface;
use Imagine\Image\Metadata\ExifMetadataReader;
use Imagine\Imagick\Imagine;
use InvalidArgumentException;
use League\Flysystem;
use League\Flysystem\FileExistsException;
use League\Flysystem\FileNotFoundException;
use League\Flysystem\FilesystemInterface;
use OverflowException;
use PDOException;
use Schoolbox\Mapper;
use Schoolbox\Mapper\Exception\InsufficientHydrationException;
use Schoolbox\Resource\Storage;
use UnexpectedValueException;

/**
 * Transforms images and persists to database + disk.
 *
 * This is a direct copy of `\Alaress\Image\Processor`: however, it accepts a
 * path and filesystem in its constructor, instead of a storage model and
 * filesystem proxy. This is because objects other than storage objects may be
 * processed.
 */
class PathProcessor
{
    /**
     * Number of seconds that ImageMagick should timeout after by default.
     * We may specify a longer time in some cases, eg from CLI, but for php-fpm requests
     * we need this value to be less than the request execution time limit (usually 60s)
     * to prevent temp files being left behind when the process is killed.
     */
    private const IMAGE_MAGICK_TIMEOUT_DEFAULT = 45;

    /**
     * Path to the file in the filesystem.
     */
    private $path;

    /**
     * The $key is a way to uniquely associate a cached image with a particular
     * series of transformations applied to it.
     *
     * It is used as the latter part of the transform's filename, eg "[hash]-[cacheKey]".
     * It is NOT the same as the transform name. A transform can take parameters and thus a single
     * transform can map to multiple cacheKeys.
     *
     * e.g. e2685ab-square64
     *
     * @var string
     */
    private $cacheKey;

    /** @var ITransform */
    private $transform;

    /**
     * Number of seconds to allow image magick to process the transform.
     *
     * * For web requests, this value must be less than the php-fpm request timeout to prevent temp files
     *   being left on disk.
     * * For CLI requests, it should be less than the Lock + max execution times.
     *
     * @var int
     */
    private int $imageMagickTimeout;


    /**
     * Constructor
     *
     * @param string                $path
     * @param FilesystemInterface   $pathFilesystem    The filesystem on which `$path` is stored
     * @param FilesystemInterface   $storageFilesystem The filesystem on which storage transforms are stored.
     *                                                 This may be a different filesystem to `$pathFilesystem`.
     * @param Storage\HashGenerator $hashGenerator     Helper for generating a storage hash out of `$path`
     * @param string                $cacheKey
     * @param ITransform            $transform
     * @param int                   $imageMagickTimeout
     */
    public function __construct(
        string $path,
        private FilesystemInterface $pathFilesystem,
        private FilesystemInterface $storageFilesystem,
        private Storage\HashGenerator $hashGenerator,
        string $cacheKey,
        ITransform $transform,
        int $imageMagickTimeout = self::IMAGE_MAGICK_TIMEOUT_DEFAULT
    ) {
        $this->path     = $path;
        $this->cacheKey = $cacheKey;
        $this->transform          = $transform;
        $this->imageMagickTimeout = $imageMagickTimeout;
    }

    /**
     * Returns a streamable resource for the given file + cache key
     *
     * If the transform does not yet exist, it is generated using the supplied transforms
     * and saved to disk and the database.
     *
     * @param int      $maxAttempts
     * @param null|int $retryAfter If non-null, retry any incomplete transformations older than this value (in seconds)
     * @param bool     $permFail   should we set the file to do NOT try again? should try on the front end, and once in cli, then fail.
     * @return StreamableFile
     * @throws DomainException
     * @throws Flysystem\FileNotFoundException
     * @throws Flysystem\FilesystemNotFoundException
     * @throws ImagineException\OutOfBoundsException
     * @throws ImagineException\RuntimeException
     * @throws InsufficientHydrationException
     * @throws InvalidArgumentException
     * @throws Mapper\Exception
     * @throws OverflowException if file's too big
     * @throws PDOException
     * @throws UnexpectedValueException
     * @throws ConcurrentTransformationException
     * @throws FileExistsException
     */
    public function load(int $maxAttempts = 1, ?int $retryAfter = null, bool $permFail = false): StreamableFile
    {
        // ensure file format is actually a string (it may have been a mimetype object)
        $mimetype   = $this->pathFilesystem->getMimetype($this->path);
        $fileFormat = $this->getFormatFromMime($mimetype);

        // Is this a SVG? just stream it if so, don't even attempt to transform
        if ($fileFormat === 'svg') {
            // Assume the stream to the base SVG exists
            return new StreamableFile(
                true,
                $mimetype,
                $this->pathFilesystem->getSize($this->path),
                $this->pathFilesystem->readStream($this->path),
            );
        }

        $hash          = $this->hashGenerator->generateHash($this->pathFilesystem->readStream($this->path));
        $transformPath = Storage\Manager::getTransformPath($hash, $this->cacheKey);

        // Fastest check (particularly when using S3)
        $transformMapper = Storage\Transform\Mapper::getInstance();
        $transformModel  = $transformMapper->findByHashAndCacheKey($hash, $this->cacheKey);
        if ($transformModel && $transformModel->isStateDone()) {
            // We cannot assume if the transform exists in the database that it
            // also exists on disk and can be immediately streamed:
            // check the file exists before attempting to stream it.
            try {
                return new StreamableFile(
                    true,
                    $mimetype,
                    $transformModel->getSize(),
                    $this->readStream($transformPath),
                );
            } catch (Flysystem\FileNotFoundException $e) {
                // Check if hash exists on disk. If it does, reset back to 'processing', if not, set to 'failed'
                $basePath = Storage\Manager::getPathFromHash($hash);
                $state    = $this->storageFilesystem->has($basePath)
                    ? Storage\Transform\Model::STATE_PROCESSING
                    : Storage\Transform\Model::STATE_FAILED;
                $transformMapper->save($transformModel->setSize(null)->setState($state));
                if ($state === Storage\Transform\Model::STATE_PROCESSING) {
                    throw new ConcurrentTransformationException($transformModel);
                }
                throw $e;
            }
        }

        // Transform exists on (local|s3) disk, but not yet in database.
        // This can occur on systems where we have not yet run the transform seeder tool
        if ($this->storageFilesystem->has($transformPath)) {
            $transformSize = $this->storageFilesystem->getSize($transformPath);
            if ($transformModel) {
                $transformMapper->save($transformModel->setSize($transformSize)->setState(Storage\Transform\Model::STATE_DONE));
            } else {
                $transformMapper->createRecord($hash, $this->cacheKey, $transformSize, Storage\Transform\Model::STATE_DONE);
            }
            return new StreamableFile(
                true,
                $mimetype,
                $transformSize,
                $this->readStream($transformPath),
            );
        }

        // Transform does not exist on disk. Has the transformation been attempted before?
        if (!$transformModel) {
            // Transformation has NOT been attempted before. We will attempt it now.
            $transformModel = $transformMapper->createRecord(
                $hash,
                $this->cacheKey,
                null,
                Storage\Transform\Model::STATE_PROCESSING,
            );
        } else {
            // Transformation HAS been attempted before.
            // If we are not retrying, or it's possible the prior attempt is still running, just return a placeholder
            if ($retryAfter === null || $transformModel->getUpdatedAt()->diffInSeconds(Alaress_Date_Format::now()) < $retryAfter) {
                throw new ConcurrentTransformationException($transformModel);
            }
            // Assume the prior execution failed. We will retry it.
            $transformMapper->save($transformModel->setState(Storage\Transform\Model::STATE_PROCESSING));
        }

        // Actually attempt the transformation.
        // If php-fpm times out, it WILL leave temp files on disk
        // If ImageMagick times out, it will NOT temp files on disk
        // In both cases the process is killed and does not return to the catch block, so both cases leave the transform with a 'processing' state.
        // If ImageMagick cannot write to temp dir, it will die without throwing an exception, so will not be caught.
        // If we're in a web request, we're constrained by a memory limit. This is often exceeded when ImageMagick finishes and returns the contents which
        // are then base64 encoded. This will not throw an exception. We could catch it in a custom shutdown handler, but it's fine to leave in the
        // 'processing' state so this is not required
        try {
            $transformSize = $this->generateTransformation(
                $maxAttempts,
                $fileFormat,
                $this->storageFilesystem,
                $transformPath,
            );
        } catch (
            FileNotFoundException
            | FileExistsException
            | ImagineException\OutOfBoundsException
            | ImagineException\RuntimeException
            | OverflowException
            | LayeredNonGifException
            | CorruptImageException $e
        ) {
            if ($permFail) {
                /**
                 * permanently fail images that error out on the cli, there will be a log for this entry to debug
                 * it might be someone has uploaded a photoshop file as .jpg
                 */
                $transformModel->setState(Storage\Transform\Model::STATE_ERROR);
            } else {
                //allow the file to be re attempted with the `tools/image/processor.php -vvv --retry-failed`
                $transformModel->setState(Storage\Transform\Model::STATE_FAILED);
            }
            $transformMapper->save($transformModel);
            throw $e;
        }

        $transformModel->setSize($transformSize)->setState(Storage\Transform\Model::STATE_DONE);
        $transformMapper->save($transformModel);

        return new StreamableFile(
            false,
            $mimetype,
            $transformModel->getSize(),
            $this->readStream($transformPath),
        );
    }


    /**
     * Transforms the image, writes it to disk, and return the transformed file's size
     *
     * @param int                 $maxAttempts How many times should we attempt to read the original file into memory
     * @param string              $fileFormat
     * @param FilesystemInterface $fileSystem
     * @param string              $transformPath
     * @return int
     * @throws CorruptImageException
     * @throws FileExistsException
     * @throws FileNotFoundException If original storage file not found on disk
     * @throws ImagickException
     * @throws ImagineException\OutOfBoundsException
     * @throws ImagineException\RuntimeException
     * @throws LayeredNonGifException
     * @throws OverflowException
     */
    private function generateTransformation(int $maxAttempts, string $fileFormat, FilesystemInterface $fileSystem, string $transformPath): int
    {
        $image = $this->loadImageFromFile($maxAttempts);
        $image = (new RotateOrientation())->manipulate($image);
        $image = $this->transform->manipulate($image);

        $options = [];
        if ($image->layers()->count() > 1) {
            if ($fileFormat !== 'gif') {
                throw new LayeredNonGifException('Animated picture is currently only supported on gif');
            }
            $options['animated']       = true;
            $options['animated.loops'] = 0;
        }
        $imageContents = $image->get($fileFormat, $options);
        $fileSystem->write($transformPath, $imageContents);
        return strlen($imageContents);
    }


    /**
     * Attempt to load an image from a file resource
     *
     * Testing indicates this operation suffers from transient failures, so support retries
     *
     * @param int $maxAttempts
     * @return ImageInterface
     * @throws OverflowException If file contents are too big
     * @throws ImagineException\RuntimeException If cannot read file into an image
     * @throws FileNotFoundException If original storage file not found on disk
     * @throws CorruptImageException If unable to parse width/height dimensions from image contents
     * @throws ImagickException If we can't set the Imagick processing timeout
     */
    private function loadImageFromFile(int $maxAttempts): ImageInterface
    {
        // If using local FileSystem + otfs_verify_previous_file is ON, and the file is missing from disk, fopen()
        // will generate a Warning that will be handled by the kernel's registered Error handler, which will throw an
        // ErrorException. This will be caught by the Kernel's ExceptionHandler and sent to Sentry, flooding it.
        // Let's detect this case and throw an exception before we attempt to open the file.
        if (!$this->pathFilesystem->has($this->path)) {
            throw new FileNotFoundException("File not found");
        }

        // Die extremely early if this is corrupted or giant
        //
        // It is important to check this here prior to attempting to read the file with ImageMagick because
        // that can segfault and not throw an exception, leading to difficult to debug errors.
        // We need to read the file into memory as it may not be local.

        // getimagesizefromstring() sometimes generates an E_WARNING in this case:
        // "corrupt JPEG data: 38 extraneous bytes before marker"
        // Since some browsers (eg Chrome) won't display the transformed image, hard fail
        $oldErrorLevel = error_reporting();
        error_reporting($oldErrorLevel | E_WARNING);
        $oldErrorHandler = set_error_handler(
            static function (int $error_number, string $error_msg) {
                throw new CorruptImageException($error_msg);
            },
            E_WARNING
        );
        $imageSize       = getimagesizefromstring($this->pathFilesystem->read($this->path));
        set_error_handler($oldErrorHandler);
        error_reporting($oldErrorLevel);
        if ($imageSize === false) {
            throw new CorruptImageException("Unable to derive image size");
        }
        [$width, $height] = $imageSize;

        // Check host's image magick limits (these are controlled by Puppet)
        $resourceLimitMap = $this->parseImageMagickResourceLimits();

        foreach ($resourceLimitMap as $name => $value) {
            switch ($name) {
                case 'width':
                    $widthLimit = Alaress_Byte_Format::stringToBytes(rtrim($value, 'P'), false);
                    if ($width > $widthLimit) {
                        throw new OverflowException("File width ($width) > limit ($widthLimit) - aborting");
                    }
                    break;
                case 'height':
                    $heightLimit = Alaress_Byte_Format::stringToBytes(rtrim($value, 'P'), false);
                    if ($height > $heightLimit) {
                        throw new OverflowException("File height ($height) > limit ($heightLimit) - aborting");
                    }
                    break;
            }
        }

        Imagick::setResourceLimit(Imagick::RESOURCETYPE_TIME, $this->imageMagickTimeout);
        $imagine  = (new Imagine())->setMetadataReader(new ExifMetadataReader());
        $image    = null;
        $attempts = 0;
        while (!$image) {
            try {
                $attempts++;
                $image = $imagine->read($this->pathFilesystem->readStream($this->path));
            } catch (ImagineException\RuntimeException $e) {
                if ($attempts > $maxAttempts) {
                    throw $e;
                }
            }
        }
        return $image;
    }

    /**
     * Parse ImageMagick's policy XML file for resource limits
     *
     * @return string[]
     */
    private function parseImageMagickResourceLimits(): array
    {
        return (new ImagickConfigParser())->parseImageMagickResourceLimits();
    }


    /**
     * Get an image format from mime
     *
     * @param string $mime
     * @return string
     * @throws DomainException
     */
    private function getFormatFromMime(string $mime): string
    {
        switch ($mime) {
            case 'image/jpeg':
            case 'image/pjpeg':
                return 'jpg';
            case 'image/gif':
                return 'gif';
            case 'image/png':
            case 'image/x-png':
                return 'png';
            case 'image/bmp':
                return 'bmp';
            case 'image/svg+xml':
                return 'svg';
            default:
                throw new DomainException("Unknown image type '$mime'");
        }
    }

    /**
     * Fetch a file stream for `$path` out of `$fileSystem`, bypassing
     * Flysystem's `disable_asserts` configuration setting.
     *
     * If `config.otfs_verify_previous_file` is on, Flysystem `disable_asserts`
     * will be on, and `readStream` won't raise an exception if the file
     * doesn't exist: it will simply return false.
     *
     * This method will re-raise the exception that would've been raised if that
     * config was off.
     *
     * TODO remove once Flysystem3 migration is done?
     *      I don't think it returns false on file not found.
     *
     * @param string $path
     * @return resource
     * @throws Flysystem\FileNotFoundException if the stream was not found
     */
    private function readStream(string $path)
    {
        $stream = $this->storageFilesystem->readStream($path);
        if (!is_resource($stream)) {
            throw new Flysystem\FileNotFoundException($path);
        }
        return $stream;
    }
}
