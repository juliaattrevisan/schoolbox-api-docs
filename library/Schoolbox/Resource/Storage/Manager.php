<?php

/**
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Resource\Storage;

use Alaress_Byte_Format;
use BadMethodCallException;
use InvalidArgumentException;
use League\Flysystem;
use League\Flysystem\FileExistsException;
use League\Flysystem\FileNotFoundException;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemInterface;
use League\Flysystem\MountManager;
use Schoolbox\File\Exception as FileException;
use Schoolbox\File\Mimetype;

use function sprintf;

/**
 * Class Manager
 * Mappers are to Models as this Manager is to files.
 * In principle, Mappers DO NOT influence the filesystem in anyway - that's what this class is for.
 * This is essentially a Schoolbox abstraction around Flysystem's MountManager.
 *
 * @package Schoolbox\Resource\Storage
 */
class Manager
{
    public const FS_STORAGE = 'storage';
    public const FS_TEMP    = 'upload';

    /** @var Alaress_Byte_Format */
    protected $alaressByteFormat;

    /** @var MountManager */
    protected $mountManager;

    /**
     * Sanitizer object, for ensuring that uploaded files are sanitized
     * appropriately.
     */
    private Sanitizer\ISanitizer $sanitizer;

    /**
     * Constructor
     *
     * @param MountManager         $mountManager
     * @param Alaress_Byte_Format  $alaressByteFormat
     * @param Sanitizer\ISanitizer $sanitizer
     */
    public function __construct(
        MountManager $mountManager,
        Alaress_Byte_Format $alaressByteFormat,
        Sanitizer\ISanitizer $sanitizer
    ) {
        $this->mountManager      = $mountManager;
        $this->alaressByteFormat = $alaressByteFormat;
        $this->sanitizer         = $sanitizer;
    }

    /**
     * Perform safety checks on file uploads performed by non superusers
     *
     * @param Model $storage
     *
     * @throws FileException
     * @throws FileNotFoundException
     */
    public function safetyCheck(Model $storage)
    {
        $fileMime = $storage->getMimetype();
        $fileName = $storage->getBasename();

        $errorSuffix = "Try a different file or contact your IT administrator.";

        // Assert: Mime set

        if (!$fileMime instanceof Mimetype) {
            throw new FileException(
                "This file has no detectable type. {$errorSuffix}",
                FileException::CODE_MIMETYPE_NOT_FOUND
            );
        }

        // Assert: Mime type allowed

        if (!$fileMime->isEnabled() || $fileMime->isDeleted()) {
            throw new FileException(
                "This file is not a valid file type. {$errorSuffix}",
                FileException::CODE_MIMETYPE_NOT_ALLOWED
            );
        }

        // Assert: Acceptable size

        if ($fileMime->getActualMaxFileSize() < $storage->getSize()) {
            $humanSize = $this->alaressByteFormat->bytesToString($fileMime->getActualMaxFileSize());
            throw new FileException(
                "This file exceeds the file size restriction of {$humanSize}. {$errorSuffix}",
                FileException::CODE_FILE_TOO_LARGE
            );
        }
    }

    /**
     * Convert the likes of "a6a0cf26c2966cb" to "a6a0c/f26c2/966cb/a6a0cf26c2966cb" for balanced storage.
     * 5 hex digits = 16^5 = 1,048,576 <= the maximum number of inodes that would exist in any one directory,
     * which is well within the acceptable limits of filesystems we use.
     *
     * @param IStorage $storage
     *
     * @return string
     */
    public function calcPath(IStorage $storage)
    {
        return self::getPathFromHash($storage->getHash());
    }

    /**
     * Returns a relative path for the specified hash
     *
     * @param string $hash
     * @return string
     */
    public static function getPathFromHash(string $hash): string
    {
        return sprintf("%s%s", chunk_split($hash, 5, '/'), $hash);
    }

    /**
     * Returns a relative path for the specified hash + cacheKey
     *
     * @param string $hash
     * @param string $cacheKey
     * @return string
     */
    public static function getTransformPath(string $hash, string $cacheKey): string
    {
        return sprintf('%s-%s', self::getPathFromHash($hash), $cacheKey);
    }

    /**
     * @param Model $storage
     *
     * @throws BadMethodCallException this method is no longer supported: use
     *                                `StorageMapper::accept` for now
     * @return bool
     */
    public function save(Model $storage)
    {
        throw new BadMethodCallException(sprintf('%s::%s is no longer supported', __CLASS__, __FUNCTION__));
//        $tempPath = $storage->getPath();
//
//        $storage
//            ->setFilesystem($this->mountManager->getFilesystem(self::FS_STORAGE))
//            ->setPath($this->calcPath($storage));
//
//        // This should already be taken care of in the mapper, but better to be safe
//        if ($storage->getPath() === $tempPath) {
//            return true;
//        }
//
//        // Attempt to move the file from temp to storage dir
//        if (!$this->move($tempPath, $storage)) {
//            throw new FileException("Failed to move uploaded file, '{$storage->getBasename()}', to storage");
//        }
//
//        return true;
    }

    /**
     * @param string $tmpFilePath
     * @param Model  $storage
     *
     * @return bool
     */
    protected function copy(string $tmpFilePath, Model $storage): bool
    {
        try {
            return $this->mountManager->copy(
                self::FS_TEMP . "://$tmpFilePath",
                self::FS_STORAGE . '://' . $storage->getPath()
            );
        } catch (FileExistsException $e) {
            // The file already exists in the storage backend.
            // This is fine, because the storage file's contents determine the
            // file path: we can assume it's the same file.
            return true;
        }
    }


    /**
     * @param string $tmpFilePath
     * @param Model  $storage
     * @return bool
     * @throws InvalidArgumentException
     * @throws Flysystem\FilesystemNotFoundException
     */
    private function move(string $tmpFilePath, Model $storage): bool
    {
        /** @var Filesystem $fsFrom */
        $fsFrom = $this->mountManager->getFilesystem(self::FS_TEMP);
        /** @var Filesystem $fsTo */
        $fsTo = $this->mountManager->getFilesystem(self::FS_STORAGE);

        $adapterFrom = $fsFrom->getAdapter();
        $adapterTo   = $fsTo->getAdapter();

        // Are they both local?
        if ($adapterFrom instanceof Flysystem\Adapter\Local && $adapterTo instanceof Flysystem\Adapter\Local) {
            // Attempt to move, rather than copy
            if ($adapterTo->createDir(dirname($storage->getPath()), $fsTo->getConfig()) === false) {
                return false;
            }

            chmod(rtrim($adapterFrom->getPathPrefix(), '/') . $tmpFilePath, 0664);
            return rename(
                rtrim($adapterFrom->getPathPrefix(), '/') . $tmpFilePath,
                $adapterTo->getPathPrefix() . $storage->getPath()
            );
        }

        try {
            return $this->mountManager->move(
                self::FS_TEMP . "://$tmpFilePath",
                self::FS_STORAGE . '://' . $storage->getPath()
            );
        } catch (FileExistsException $e) {
            // The file already exists in the storage backend.
            // This is fine, because the storage file's contents determine the
            // file path: we can assume it's the same file.
            try {
                $fsFrom->delete($tmpFilePath);
            } catch (FileNotFoundException $e2) {
                // The temp file didn't exist after all: that's fine too
            }
            return true;
        }
    }

    /**
     * Sanitize file prior to storing.
     *
     * @param Model               $storage    The storage object for the file: required so
     *                                        that the sanitizer knows how to sanitise the
     *                                        file.
     * @param FilesystemInterface $filesystem The filesystem the file to sanitize exists on.
     * @param string              $path       The path to the file to sanitize.
     * @return string|null Sanitized content if the item was sanitized, null if
     *                     it was not sanitized.
     * @throws FileNotFoundException
     */
    public function sanitize(
        Model $storage,
        FilesystemInterface $filesystem,
        string $path,
    ): ?string {
        if (!$this->sanitizer->accepts($storage->getMime())) {
            return null;
        }
        try {
            $unsafeContent = $filesystem->read($path);
        } catch (FileNotFoundException $e) {
            return null;
        }
        $sanitizedContent = $this->sanitizer->sanitize($storage->getMime(), $unsafeContent);
        if (!isset($sanitizedContent) || $unsafeContent === $sanitizedContent) {
            return null;
        }
        return $sanitizedContent;
//        if ($dryRun) {
//            return $sanitizedContent;
//        }
//        // update storage to match sanitized content
//        $result = $filesystem->put($path, $sanitizedContent);
//        $storage->setHash((new HashGenerator())->generateHash($filesystem->readStream($path)));
//        return $result;
    }

    /**
     * @param Model $storage
     *
     * @return bool
     */
    public function delete(Model $storage)
    {
        return $storage->getFilesystem()->deleteDir(dirname($storage->getPath()));
    }

    /**
     * Passthrough method
     *
     * @param string $prefix
     *
     * @return FilesystemInterface
     */
    public function getFilesystem($prefix)
    {
        return $this->mountManager->getFilesystem($prefix);
    }

    /**
     * Convenience method for fetching storage filesystem.
     *
     * @return FilesystemInterface
     */
    public function getStorageFilesystem(): FilesystemInterface
    {
        return $this->getFilesystem(self::FS_STORAGE);
    }

    /**
     * Convenience method for fetching temp filesystem.
     *
     * @return FilesystemInterface
     */
    public function getTempFilesystem(): FilesystemInterface
    {
        return $this->getFilesystem(self::FS_TEMP);
    }
}
