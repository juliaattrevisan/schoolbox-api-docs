<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Image;

use Alaress_Byte_Format;
use DomainException;
use Imagine\Exception as ImagineException;
use Alaress\Image\Transform\RotateOrientation;
use Imagine\Image\Metadata\ExifMetadataReader;
use Imagine\Imagick\Imagine;
use League\Flysystem;
use League\Flysystem\FileExistsException;
use OverflowException;
use PDOException;
use Schoolbox\Config\Loader;
use Schoolbox\Mapper;
use Schoolbox\Resource\FilesystemFactory;
use Schoolbox\Resource\Storage;
use UnexpectedValueException;

class Processor
{
    /**
     * Original un-transformed file. Contents may be local or remote.
     *
     * @var Storage\Model
     */
    private $file;

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
     * Constructor
     *
     * @param Storage\Model $file
     * @param string        $cacheKey
     * @param ITransform    $transform
     */
    public function __construct(Storage\Model $file, string $cacheKey, ITransform $transform)
    {
        $this->file      = $file;
        $this->cacheKey  = $cacheKey;
        $this->transform = $transform;
    }

    /**
     * Returns a streamable resource for the given file + cache key
     *
     * If the transform does not yet exist, it is generated using the supplied transforms
     * and saved to disk and the database.
     *
     * @return StreamableFile
     * @throws Flysystem\FileNotFoundException
     * @throws Flysystem\FilesystemNotFoundException
     * @throws DomainException
     * @throws ImagineException\OutOfBoundsException
     * @throws ImagineException\RuntimeException
     * @throws OverflowException if file's too big
     * @throws PDOException
     * @throws Mapper\Exception
     * @throws UnexpectedValueException
     */
    public function load(): StreamableFile
    {
        // TODO SA-8354 inject dependencies
        $mountManager   = (new FilesystemFactory())->build((new Loader())->get('storage_dir'));
        $fileSystem     = $mountManager->getFilesystem(Storage\Manager::FS_STORAGE);
        $storageManager = new Storage\Manager($mountManager, new Alaress_Byte_Format());
        $transformPath  = $storageManager::getTransformPath($this->file->getHash(), $this->cacheKey);

        // Fastest check (particularly when using S3)
        // We can assume if the transform exists in the database that it also exists on disk and can be immediately streamed.
        $transformMapper = Storage\Transform\Mapper::getInstance();
        $transformModel  = $transformMapper->findByHashAndCacheKey($this->file->getHash(), $this->cacheKey);
        if ($transformModel) {
            return new StreamableFile(true, (string)$this->file->getMimetype(), $transformModel->getSize(), $fileSystem->readStream($transformPath));
        }

        // Transform exists on (local|s3) disk, but not yet in database.
        // This can occur on systems where we have not yet run the transform seeder tool
        if ($fileSystem->has($transformPath)) {
            $transformSize = $fileSystem->getSize($transformPath);
            $transformMapper->createRecord($this->file->getHash(), $this->cacheKey, $transformSize);
            return new StreamableFile(true, (string)$this->file->getMimetype(), $transformSize, $fileSystem->readStream($transformPath));
        }

        // Transform does not yet exist. Generate and save it to disk and database
        // Die extremely early if this is corrupted or giant
        // Figure out an image size in megapixels
        [$w, $h] = getimagesizefromstring($this->file->read());
        $megapixelSize = ($w * $h) / 1000000;
        if ($megapixelSize >= 50) {
            // error out
            throw new OverflowException('File is over 50MP large - aborting');
        }

        $imagine = (new Imagine())->setMetadataReader(new ExifMetadataReader());
        $image = $imagine->read($this->file->readStream());

        $image = (new RotateOrientation())->manipulate($image);
        $image = $this->transform->manipulate($image);

        // ensure this is actually a string (it may have been a mimetype object)
        $fileFormat = $this->getFormatFromMime((string)$this->file->getMimetype());

        $options = [];
        if ($image->layers()->count() > 1) {
            $options['animated']       = true;
            $options['animated.loops'] = 0;
        }
        $imageContents = $image->get($fileFormat, $options);

        try {
            // TODO This occurs because we can have multiple processes simultaneously doing the same  transform.
            // TODO We will add a mutex to fix this.
            // @see https://schoolbox.atlassian.net/browse/SA-11227
            $fileSystem->write($transformPath, $imageContents);
        } catch (FileExistsException $e) {
            // Just ignore for now.
        }

        $transformSize = strlen($imageContents);
        $transformMapper->createRecord(
            $this->file->getHash(),
            $this->cacheKey,
            $transformSize
        );

        return new StreamableFile(false, (string)$this->file->getMimetype(), $transformSize, $fileSystem->readStream($transformPath));
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
            default:
                throw new DomainException("Unknown image type '$mime'");
        }
    }
}
