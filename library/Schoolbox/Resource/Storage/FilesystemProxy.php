<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Resource\Storage;

use League\Flysystem\DirectoryListing;
use League\Flysystem\FilesystemException;
use League\Flysystem\UnableToCheckDirectoryExistence;
use League\Flysystem\UnableToCopyFile;
use League\Flysystem\UnableToCreateDirectory;
use League\Flysystem\UnableToDeleteDirectory;
use League\Flysystem\UnableToMoveFile;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\Filesystem;
use League\Flysystem\MountManager;
use League\Flysystem\UnableToWriteFile;
use Schoolbox\Resource\FilesystemFactory;

/**
 * A proxy class for providing a `Filesystem`-like facade when dealing
 * with `IStorage` objects, instead of paths.
 *
 * Directory-specific methods are not proxied, because directories are not
 * relevant for `IStorage` objects. All of these methods raise a
 * directory-related exception.
 *
 * Location-specific methods are not proxied, because an `IStorage` object's
 * location is determined by its hash. All of these methods raise a
 * copy or move related exception.
 */
class FilesystemProxy
{
    /**
     * Generic exception for non-supported directory operations.
     */
    private const ERROR_DIRECTORY_SPECIFIC_METHOD = __CLASS__ . ' does not support directory methods';

    /**
     * Generic exception for non-supported location operations.
     */
    private const ERROR_LOCATION_SPECIFIC_METHOD = __CLASS__ . ' does not support location methods';

    /**
     * Constructor
     *
     * @param Filesystem    $filesystem         Base filesystem this class proxies.
     * @param HashGenerator $hashGenerator      Method for generating hashes for storage files.
     * @param bool          $verifyPrevious     Should files uploaded via `accept` or `acceptStream` be verified?
     * @param string        $defaultVisibility  Default visibility for newly uploaded files; should be:
     *                                          * `\League\Flysystem\Visibility::PUBLIC` for local filesystem
     *                                          * `\League\Flysystem\Visibility::PRIVATE` for S3
     * @param bool          $isLocal            Is the proxy storing file locally?
     * @param ?string       $localPath          Path to the local storage folder, or if stored remotely, the folder for
     *                                          local cache storage folder
     * @param ?Filesystem   $fallbackFilesystem If provided: when deleting a storage object, attempt to delete from this
     *                                          filesystem as well as `$filesystem`.
     *                                          The R2 filesystem acts as a read and write through cache for S3, but R2
     *                                          cannot sync deletion of files which don't exist: therefore, the files
     *                                          must be deleted manually from both filesystems.
     */
    public function __construct(
        private Filesystem $filesystem,
        private HashGenerator $hashGenerator,
        private bool $verifyPrevious,
        private string $defaultVisibility,
        private bool $isLocal,
        private ?string $localPath,
        private ?Filesystem $fallbackFilesystem,
    ) {
    }

    /**
     * Proxy for:
     * * v1 `FilesystemInterface::has` (with file name)
     * * v3 `Filesystem::fileExists`
     *
     * @param IStorage $storage
     * @return bool
     * @throws FilesystemException
     */
    public function fileExists(IStorage $storage): bool
    {
        return $this->filesystem->fileExists(self::getPathFromHash($storage->getHash()));
    }

    /**
     * Proxy for:
     * * v1 `FilesystemInterface::has` (with folder name)
     * * v3 `Filesystem::directoryExists`
     *
     * @param IStorage $storage
     * @return never-return
     * @throws UnableToCheckDirectoryExistence
     * @noinspection PhpUnusedParameterInspection
     */
    public function directoryExists(IStorage $storage): void
    {
        throw new UnableToCheckDirectoryExistence(self::ERROR_DIRECTORY_SPECIFIC_METHOD);
    }

    /**
     * Proxy for:
     * * v1 `FilesystemInterface::write`
     * * v3 `Filesystem::write`
     *
     * @param IStorage $storage
     * @param string   $contents
     * @param array    $config
     * @return void
     * @throws FilesystemException
     */
    public function write(IStorage $storage, string $contents, array $config = [])
    {
        $fp = fopen('php://temp', 'r+');
        fwrite($fp, $contents);
        rewind($fp);
        $this->writeStream($storage, $fp, $config);
    }

    /**
     * Proxy for:
     * * v1 `FilesystemInterface::writeStream`
     * * v3 `Filesystem::writeStream`
     *
     * @param IStorage $storage
     * @param resource $resource
     * @param array    $config
     * @return void
     * @throws FilesystemException
     */
    public function writeStream(IStorage $storage, $resource, array $config = [])
    {
        if ($this->shortCircuit($storage)) {
            return;
        }

        $hash = $this->hashGenerator->generateHash($resource);
        $path = self::getPathFromHash($hash);

        // umask setting may prevent group/other-writable files:
        // storage files are supposed to be group-writable though, so switch off
        // the umask and restore it once done.
        $oldUmask = umask(0);
        try {
            // The file may already exist in the storage backend.
            // This is fine, because the storage file's contents determine the
            // file path: we can assume it's the same file.
            if (!$this->filesystem->fileExists($path)) {
                $this->filesystem->writeStream($path, $resource, $config);
            }
            $this->updateStorage($storage, $hash, $path);
        } finally {
            umask($oldUmask);
        }
    }

    /**
     * Efficient method for writing from a filesystem into a storage file.
     *
     * Detects if `$this->filesystem` and the source filesystem are the same,
     * and if so performs a rename instead of a move.
     *
     * @param IStorage   $storage
     * @param Filesystem $tempFilesystem
     * @param string     $tempPath
     * @return void
     * @throws FilesystemException
     */
    public function writeFromFilesystemAndLocation(
        IStorage $storage,
        Filesystem $tempFilesystem,
        string $tempPath
    ) {
        if ($this->shortCircuit($storage)) {
            return;
        }

        $hash        = $this->hashGenerator->generateHash($tempFilesystem->readStream($tempPath));
        $storagePath = self::getPathFromHash($hash);

        // umask setting may prevent group/other-writable files:
        // storage files are supposed to be group-writable though, so switch off
        // the umask and restore it once done.
        $oldUmask = umask(0);
        try {
            // The file may already exist in the storage backend.
            // This is fine, because the storage file's contents determine the
            // file path: we can assume it's the same file.
            // We don't want to store a new copy if file already existed, more specifically to avoid saving
            // identical files as new versions in S3
            if (!$this->filesystem->fileExists($storagePath)) {
                // Create the new folder with the filesystem's default visibility
                // (public for local, private for s3)
                $this->filesystem->createDirectory(dirname($storagePath));

                // Copy the new file into storage
                $mountManager = new MountManager(
                    [
                        FilesystemFactory::FILE_SYSTEM_TEMP    => $tempFilesystem,
                        FilesystemFactory::FILE_SYSTEM_STORAGE => $this->filesystem,
                    ]
                );

                // Moving a file using the mount manager without config overrides any existing
                // file's visibility. Need to explicitly provide visibility
                // (public for local, private for s3)
                $config = ['visibility' => $this->defaultVisibility];
                $mountManager->move(
                    FilesystemFactory::FILE_SYSTEM_TEMP . "://" . $tempPath,
                    FilesystemFactory::FILE_SYSTEM_STORAGE . '://' . $storagePath,
                    $config
                );
            }
            $this->updateStorage($storage, $hash, $storagePath);
        } finally {
            umask($oldUmask);
        }
    }

    /**
     * Proxy for:
     * * v1 `FilesystemInterface::read`
     * * v3 `Filesystem::read`
     *
     * @param IStorage $storage
     * @return string
     * @throws FilesystemException
     */
    public function read(IStorage $storage): string
    {
        return $this->filesystem->read(self::getPathFromHash($storage->getHash()));
    }

    /**
     * Proxy for:
     * * v1 `FilesystemInterface::readStream`
     * * v3 `Filesystem::readStream`
     *
     * @param IStorage $storage
     * @return resource
     * @throws FilesystemException
     */
    public function readStream(IStorage $storage)
    {
        return $this->filesystem->readStream(self::getPathFromHash($storage->getHash()));
    }

    /**
     * Return file path for the storage item, if file not found then return false
     *
     * @param IStorage $storage
     * @return bool|string
     *
     * @throws FilesystemException
     */
    public function localPath(IStorage $storage): bool|string
    {

        /* Not catching the exception here so that it can scream out as loud as it can
         */
        $fileContent = $this->read($storage);

        if ($this->isLocal) {
            /* Return local storage path if files are stored locally
             */
            return $this->localPath . '/' . self::getPathFromHash($storage->getHash());
        }

        /* Create a copy of the remote file and return the local path
         */
        $localCachePath = sprintf(
            "%s/local_%s_%s.%s",
            $this->localPath,
            INSTANCE_ID,
            $storage->getHash(),
            $storage->getExtension(),
        );
        if (!file_exists($localCachePath)) {
            if (false === file_put_contents($localCachePath, $fileContent)) {
                throw new UnableToWriteFile("Local cache file could not be saved.");
            }
        }

        return $localCachePath;
    }

    /**
     * Proxy for:
     * * v1 `FilesystemInterface::delete` (with file name)
     * * v3 `Filesystem::delete`
     *
     * @param IStorage $storage
     * @throws FilesystemException
     */
    public function delete(IStorage $storage): void
    {
        $dirname = dirname(self::getPathFromHash($storage->getHash()));

        // Deleting the parent folder of the file deletes all of its transforms
        $this->filesystem->deleteDirectory($dirname);

        // Also delete from the backup filesystem, if it's there
        $this->fallbackFilesystem?->deleteDirectory($dirname);

        // Do not delete storage record here
    }

    /**
     * Proxy for:
     * * v1 `FilesystemInterface::delete` (with folder name)
     * * v3 `Filesystem::deleteDirectory`
     *
     * @param IStorage $storage
     * @return never-return
     * @throws UnableToDeleteDirectory
     * @noinspection PhpUnusedParameterInspection
     */
    public function deleteDirectory(IStorage $storage): void
    {
        throw new UnableToDeleteDirectory(self::ERROR_DIRECTORY_SPECIFIC_METHOD);
    }

    /**
     * Proxy for:
     * * v1 `FilesystemInterface::createDir` (with folder name)
     * * v3 `Filesystem::createDirectory`
     *
     * @param IStorage $storage
     * @param array    $config
     * @return never-return
     * @throws UnableToCreateDirectory
     * @noinspection PhpUnusedParameterInspection
     */
    public function createDirectory(IStorage $storage, array $config = []): void
    {
        throw new UnableToCreateDirectory(self::ERROR_DIRECTORY_SPECIFIC_METHOD);
    }

    /**
     * Proxy for:
     * * v1 `FilesystemInterface::setVisibility`
     * * v3 `Filesystem::setVisibility`
     *
     * @param IStorage $storage
     * @param string   $visibility
     * @throws FilesystemException
     */
    public function setVisibility(IStorage $storage, string $visibility): void
    {
        $this->filesystem->setVisibility(self::getPathFromHash($storage->getHash()), $visibility);
    }

    /**
     * Proxy for:
     * * v1 `FilesystemInterface::setVisibility`
     * * v3 `Filesystem::visibility`
     *
     * @param IStorage $storage
     * @return string
     * @throws FilesystemException
     */
    public function visibility(IStorage $storage): string
    {
        return $this->filesystem->visibility(self::getPathFromHash($storage->getHash()));
    }

    /**
     * Proxy for:
     * * v1 `FilesystemInterface::getMimetype`
     * * v3 `Filesystem::mimeType`
     *
     * @param IStorage $storage
     * @return string
     * @throws FilesystemException
     */
    public function mimeType(IStorage $storage): string
    {
        return $this->filesystem->mimeType(self::getPathFromHash($storage->getHash()));
    }

    /**
     * Proxy for:
     * * v1 `FilesystemInterface::getTimestamp`
     * * v3 `Filesystem::lastModified`
     *
     * @param IStorage $storage
     * @return int
     * @throws FilesystemException
     */
    public function lastModified(IStorage $storage): int
    {
        return $this->filesystem->lastModified(self::getPathFromHash($storage->getHash()));
    }

    /**
     * Proxy for:
     * * v1 `FilesystemInterface::getSize`
     * * v3 `Filesystem::fileSize`
     *
     * @param IStorage $storage
     * @return int
     * @throws UnableToReadFile
     */
    public function fileSize(IStorage $storage): int
    {
        return $this->filesystem->fileSize(self::getPathFromHash($storage->getHash()));
    }

    /**
     * Proxy for:
     * * v1 `FilesystemInterface::listContents`.
     * * v3 `Filesystem::listContents`
     *
     * @param IStorage $storage
     * @param bool     $recursive
     * @return DirectoryListing
     * @throws UnableToCheckDirectoryExistence
     * @noinspection PhpUnusedParameterInspection
     */
    public function listContents(IStorage $storage, bool $recursive = false): DirectoryListing
    {
        throw new UnableToCheckDirectoryExistence(self::ERROR_DIRECTORY_SPECIFIC_METHOD);
    }

    /**
     * Proxy for:
     * * v1 `FilesystemInterface::rename`
     * * v3 `Filesystem::move`
     *
     * @param IStorage $source
     * @param IStorage $destination
     * @return never-return
     * @throws UnableToMoveFile
     * @noinspection PhpUnusedParameterInspection
     */
    public function move(IStorage $source, IStorage $destination): void
    {
        throw new UnableToMoveFile(self::ERROR_LOCATION_SPECIFIC_METHOD);
    }

    /**
     * Proxy for:
     * * v1 `FilesystemInterface::copy`
     * * v3 `Filesystem::copy`
     *
     * @param IStorage $source
     * @param IStorage $destination
     * @return never-return
     * @throws UnableToCopyFile
     * @noinspection PhpUnusedParameterInspection
     */
    public function copy(IStorage $source, IStorage $destination): void
    {
        throw new UnableToCopyFile(self::ERROR_LOCATION_SPECIFIC_METHOD);
    }

    /**
     * Returns a filesystem path for the specified hash.
     *
     * @param string $hash
     * @return string
     */
    public static function getPathFromHash(string $hash): string
    {
        return sprintf("%s%s", chunk_split($hash, 5, '/'), $hash);
    }

    /**
     * Returns a filesystem path for the specified hash + cache key.
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
     * Should file upload be short-circuited?
     *
     * Short-circuit if `$storage` already has a hash, and either:
     * * `$verifyPrevious` is off
     * * the file already exists in storage, and its size is as it should be
     * TODO determine if a storage file could ever have its hash modified
     *      (doesn't saving a new file always create a new storage record?)
     *
     * @param IStorage $storage
     * @return bool
     * @throws FilesystemException
     */
    private function shortCircuit(IStorage $storage): bool
    {
        $previousHash = $storage->getHash();
        if ($previousHash) {
            if (!$this->verifyPrevious) {
                return true;
            }
            $previousPath = self::getPathFromHash($previousHash);
            if (
                $this->filesystem->has($previousPath)
                && $storage->getSize() === $this->filesystem->fileSize($previousPath)
            ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Update the storage record's hash and file size.
     *
     * @param IStorage $storage
     * @param string   $hash
     * @param string   $path
     * @return void
     * @throws FilesystemException
     */
    private function updateStorage(IStorage $storage, string $hash, string $path): void
    {
        if ($storage instanceof Model) {
            $storage
                ->setHash($hash)
                ->setSize($this->filesystem->fileSize($path));
        }
    }
}
