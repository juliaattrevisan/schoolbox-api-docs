<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Image;

use League\Flysystem\Adapter\Local;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemInterface;
use Schoolbox\Resource\Storage\HashGenerator;
use Schoolbox\Resource\Storage\IStorage;
use Schoolbox\Resource\Storage\Manager;

/**
 * Factory class for building image processors, which are used for generating
 * transforms.
 */
class ProcessorFactory
{
    /**
     * Constructor
     *
     * @param FilesystemInterface $storageFilesystem The storage file system, required when processing both local and
     *                                               storage files (as transforms are stored in storage regardless of
     *                                               whether the base file is).
     * @param HashGenerator       $hashGenerator     The hash generator, required for converting a storage file to a
     *                                               path.
     */
    public function __construct(
        private FilesystemInterface $storageFilesystem,
        private HashGenerator $hashGenerator,
    ) {
    }

    /**
     * Build a processor for a local file path.
     *
     * @param string     $baseDir   The directory in which the file to transform
     *                              lives.
     * @param string     $file      The file to transform.
     * @param string     $cacheKey  The key for identifying a set of transforms
     *                              applied to the file.
     * @param ITransform $transform The set of transforms applied to the file.
     * @param int        $timeout   Time (in seconds) which the transform is
     *                              allowed to take.
     * @return Processor
     */
    public function buildForLocalFile(
        string $baseDir,
        string $file,
        string $cacheKey,
        ITransform $transform,
        int $timeout = Processor::IMAGE_MAGICK_TIMEOUT_DEFAULT,
    ): Processor {
        return new Processor(
            $file,
            null,
            new Filesystem(new Local($baseDir)),
            $this->storageFilesystem,
            $this->hashGenerator,
            $cacheKey,
            $transform,
            $timeout,
        );
    }

    /**
     * Build a processor for a storage object.
     *
     * @param IStorage   $storage   The storage object to transform.
     * @param string     $cacheKey  The key for identifying a set of transforms
     *                              applied to the storage object.
     * @param ITransform $transform The set of transforms applied to the storage
     *                              object.
     * @param int        $timeout   Time (in seconds) which the transform is
     *                              allowed to take.
     * @return Processor
     */
    public function buildForStorage(
        IStorage $storage,
        string $cacheKey,
        ITransform $transform,
        int $timeout = Processor::IMAGE_MAGICK_TIMEOUT_DEFAULT,
    ): Processor {
        return new Processor(
            Manager::getPathFromHash($storage->getHash()),
            $storage->getMime(),
            $this->storageFilesystem,
            $this->storageFilesystem,
            $this->hashGenerator,
            $cacheKey,
            $transform,
            $timeout,
        );
    }
}
