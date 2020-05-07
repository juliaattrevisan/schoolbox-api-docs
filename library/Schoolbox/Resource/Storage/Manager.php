<?php

/**
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Resource\Storage;

use Alaress_Byte_Format;
use League\Flysystem\FileExistsException;
use League\Flysystem\FileNotFoundException;
use League\Flysystem\FilesystemInterface;
use League\Flysystem\MountManager;
use Schoolbox\File\Exception as FileException;
use Schoolbox\File\Mimetype;
use enshrined\svgSanitize\Sanitizer;

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
     * @param MountManager        $mountManager
     * @param Alaress_Byte_Format $alaressByteFormat
     */
    public function __construct(MountManager $mountManager, Alaress_Byte_Format $alaressByteFormat)
    {
        $this->mountManager      = $mountManager;
        $this->alaressByteFormat = $alaressByteFormat;
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
     * @throws FileException
     * @return bool
     */
    public function save(Model $storage)
    {
        $tempPath = $storage->getPath();

        $storage
            ->setFilesystem($this->mountManager->getFilesystem(self::FS_STORAGE))
            ->setPath($this->calcPath($storage));

        // This should already be taken care of in the mapper, but better to be safe

        if ($storage->getPath() == $tempPath) {
            return true;
        }

        // Attempt to copy the file

        if (!$this->copy($tempPath, $storage)) {
            throw new FileException("Failed to copy uploaded file, '{$storage->getBasename()}', to storage");
        }

        if (!unlink(sys_get_temp_dir() . $tempPath)) {
            trigger_error('Unable to delete temp file', E_USER_WARNING);
        }

        return true;
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
            if (strtolower((string)$storage->getMimetype()) === 'image/svg+xml') {
                $this->sanitize(self::FS_TEMP, $tmpFilePath);
            }
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
     * Sanitize before storing
     * @param string $fileSystem
     * @param string $path
     * @param bool   $dryRun
     * @return bool
     */
    public function sanitize(string $fileSystem, string $path, bool $dryRun = false): bool
    {
        // purify before storing, if the file is svg
        $sanitizer = new Sanitizer();
        $sanitizer->minify(true);
        try {
            $rawSVG = $this->mountManager->getFilesystem($fileSystem)->read($path);
        } catch (FileNotFoundException $e) {
            return false;
        }
        $purifiedSVG = $sanitizer->sanitize($rawSVG);
        if (!$purifiedSVG || $rawSVG === $purifiedSVG) {
            return false;
        }
        if ($dryRun) {
            return true;
        }
        return $this->mountManager->getFilesystem($fileSystem)->put($path, $purifiedSVG);
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
}
