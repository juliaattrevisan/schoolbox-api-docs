<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Resource\Storage;

use League\Flysystem\Adapter\Local;
use League\Flysystem\FileExistsException;
use League\Flysystem\FileNotFoundException;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\MountManager;
use League\Flysystem\NotSupportedException;
use Schoolbox\Resource\FilesystemFactory;
use Schoolbox\Resource\Storage\S3\Adapter as S3Adapter;

/**
 * A proxy class for providing a `FilesystemAdapter`-like facade when dealing
 * with `IStorage` objects, instead of paths.
 *
 * This provides a Flysystem v3-like proxy, even though currently it wraps a
 * v1 Flysystem object. Where the two interfaces differ, v3 will be used.
 *
 * Directory-specific methods are not proxied, because directories are not
 * relevant for `IStorage` objects. All of these methods raise a
 * `NotSupportedException`.
 *
 * Location-specific methods are not proxied, because an `IStorage` object's
 * location is determined by its hash. All of these methods raise a
 * `NotSupportedException`.
 */
class FilesystemProxy
{
    /**
     * Constructor
     *
     * @param FilesystemAdapter $filesystem     Base filesystem this class proxies.
     * @param HashGenerator     $hashGenerator  Method for generating hashes for storage files.
     * @param bool              $verifyPrevious Should files uploaded via `accept` or `acceptStream` be
     */
    public function __construct(
        private FilesystemAdapter $filesystem,
        private HashGenerator $hashGenerator,
        private bool $verifyPrevious,
    ) {
    }

    /**
     * Proxy for:
     * * v1 `FilesystemInterface::has` (with file name)
     * * v3 `FilesystemAdapter::fileExists`
     *
     * @param IStorage $storage
     * @return bool
     */
    public function fileExists(IStorage $storage): bool
    {
        return $this->filesystem->has(self::getPathFromHash($storage->getHash()));
    }

    /**
     * Proxy for:
     * * v1 `FilesystemInterface::has` (with folder name)
     * * v3 `FilesystemAdapter::directoryExists`
     *
     * @param IStorage $storage
     * @return never-return
     * @throws NotSupportedException
     * @noinspection PhpUnusedParameterInspection
     */
    public function directoryExists(IStorage $storage): void
    {
        throw self::generateDirectorySpecificMethodException();
    }

    /**
     * Proxy for:
     * * v1 `FilesystemInterface::write`
     * * v3 `FilesystemAdapter::write`
     *
     * @param IStorage $storage
     * @param string   $contents
     * @param array    $config
     * @return void
     * @throws FileNotFoundException if somehow the file wasn't found after it
     *                               was written
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
     * * v3 `FilesystemAdapter::writeStream`
     *
     * @param IStorage $storage
     * @param resource $resource
     * @param array    $config
     * @return void
     * @throws FileNotFoundException if somehow the file wasn't found after it
     *                               was written
     */
    public function writeStream(IStorage $storage, $resource, array $config = [])
    {
        if ($this->shortCircuit($storage)) {
            return;
        }

        $hash = $this->hashGenerator->generateHash($resource);
        $path = self::getPathFromHash($hash);
        try {
            $this->filesystem->writeStream($path, $resource, $config);
        } catch (FileExistsException $e) {
            // The file already exists in the storage backend.
            // This is fine, because the storage file's contents determine the
            // file path: we can assume it's the same file.
        }

        // Put it in the filesystem, and also the storage
        // (IStorage doesn't have setHash? Maybe only if $storage is a storage model, then set it?)
        $this->updateStorage($storage, $hash, $path);
    }

    /**
     * Efficient method for writing from a filesystem into a storage file.
     *
     * Detects if `$this->filesystem` and the source filesystem are the same,
     * and if so performs a rename instead of a move.
     *
     * @param IStorage          $storage
     * @param FilesystemAdapter $tempFilesystem
     * @param string            $tempPath
     * @return void
     * @throws FileNotFoundException if somehow the file doesn't exist in temp?
     */
    public function writeFromFilesystemAndLocation(
        IStorage $storage,
        FilesystemAdapter $tempFilesystem,
        string $tempPath
    ) {
        if ($this->shortCircuit($storage)) {
            return;
        }

        $hash        = $this->hashGenerator->generateHash($tempFilesystem->readStream($tempPath));
        $storagePath = self::getPathFromHash($hash);

        // Are they both local? Attempt to move, rather than copy if so
        if ($tempFilesystem instanceof Filesystem && $this->filesystem instanceof Filesystem) {
            $adapterFrom = $tempFilesystem->getAdapter();
            $adapterTo   = $this->filesystem->getAdapter();
            if ($adapterFrom instanceof Local && $adapterTo instanceof Local) {
                if ($adapterTo->createDir(dirname($storagePath), $this->filesystem->getConfig()) === false) {
                    // TODO is this an error?
                    return;
                }

                chmod(rtrim($adapterFrom->getPathPrefix(), '/') . $tempPath, 0664);
                rename(
                    rtrim($adapterFrom->getPathPrefix(), '/') . $tempPath,
                    $adapterTo->getPathPrefix() . $storagePath
                );
                $this->updateStorage($storage, $hash, $storagePath);
                return;
            }
        }

        // Otherwise, copy the new file into storage
        try {
            $mountManager = new MountManager([
                FilesystemFactory::FILE_SYSTEM_TEMP    => $tempFilesystem,
                FilesystemFactory::FILE_SYSTEM_STORAGE => $this->filesystem,
            ]);
            $mountManager->move(
                FilesystemFactory::FILE_SYSTEM_TEMP . "://" . $tempPath,
                FilesystemFactory::FILE_SYSTEM_STORAGE . '://' . $storagePath,
            );
            // @phpstan-ignore-next-line: move() can throw this exception
        } catch (FileExistsException $e) {
            // The file already exists in the storage backend.
            // This is fine, because the storage file's contents determine the
            // file path: we can assume it's the same file.
            try {
                $tempFilesystem->delete($tempPath);
            } catch (FileNotFoundException $e2) {
                // The temp file didn't exist after all: that's fine too
            }
        }
        $this->updateStorage($storage, $hash, $storagePath);
    }

    /**
     * Proxy for:
     * * v1 `FilesystemInterface::read`
     * * v3 `FilesystemAdapter::read`
     *
     * @param IStorage $storage
     * @return string
     * @throws FileNotFoundException if the file wasn't found
     */
    public function read(IStorage $storage): string
    {
        return $this->filesystem->read(self::getPathFromHash($storage->getHash()));
    }

    /**
     * Proxy for:
     * * v1 `FilesystemInterface::readStream`
     * * v3 `FilesystemAdapter::readStream`
     *
     * @param IStorage $storage
     * @return resource
     * @throws FileNotFoundException
     */
    public function readStream(IStorage $storage)
    {
        return $this->filesystem->readStream(self::getPathFromHash($storage->getHash()));
    }


    /**
     * Proxy for:
     * * v1 `FilesystemInterface::delete` (with file name)
     * * v3 `FilesystemAdapter::delete`
     *
     * @param IStorage $storage
     */
    public function delete(IStorage $storage): void
    {
        // Deleting the parent folder of the file deletes all of its transforms
        $this->filesystem->deleteDir(dirname(self::getPathFromHash($storage->getHash())));

        // Do not delete storage record here
    }

    /**
     * Proxy for:
     * * v1 `FilesystemInterface::delete` (with folder name)
     * * v3 `FilesystemAdapter::deleteDirectory`
     *
     * @param IStorage $storage
     * @return never-return
     * @throws NotSupportedException
     * @noinspection PhpUnusedParameterInspection
     */
    public function deleteDirectory(IStorage $storage): void
    {
        throw self::generateDirectorySpecificMethodException();
    }

    /**
     * Proxy for:
     * * v1 `FilesystemInterface::createDir` (with folder name)
     * * v3 `FilesystemAdapter::createDirectory`
     *
     * @param IStorage $storage
     * @param array    $config
     * @return never-return
     * @throws NotSupportedException
     * @noinspection PhpUnusedParameterInspection
     */
    public function createDirectory(IStorage $storage, array $config = []): void
    {
        throw self::generateDirectorySpecificMethodException();
    }

    /**
     * Proxy for:
     * * v1 `FilesystemInterface::setVisibility`
     * * v3 `FilesystemAdapter::setVisibility`
     *
     * @param IStorage $storage
     * @param string   $visibility
     * @throws FileNotFoundException
     */
    public function setVisibility(IStorage $storage, string $visibility): void
    {
        $this->filesystem->setVisibility(self::getPathFromHash($storage->getHash()), $visibility);
    }

    /**
     * Proxy for:
     * * v1 `FilesystemInterface::setVisibility`
     * * v3 `FilesystemAdapter::visibility`
     *
     * @param IStorage $storage
     * @return string
     * @throws FileNotFoundException
     */
    public function visibility(IStorage $storage): string
    {
        return $this->filesystem->getVisibility(self::getPathFromHash($storage->getHash()));
    }

    /**
     * Proxy for:
     * * v1 `FilesystemInterface::getMimetype`
     * * v3 `FilesystemAdapter::mimeType`
     *
     * @param IStorage $storage
     * @return string
     * @throws FileNotFoundException
     */
    public function mimeType(IStorage $storage): string
    {
        return $this->filesystem->getMimetype(self::getPathFromHash($storage->getHash()));
    }

    /**
     * Proxy for:
     * * v1 `FilesystemInterface::getTimestamp`
     * * v3 `FilesystemAdapter::lastModified`
     *
     * @param IStorage $storage
     * @return int
     * @throws FileNotFoundException
     */
    public function lastModified(IStorage $storage): int
    {
        return $this->filesystem->getTimestamp(self::getPathFromHash($storage->getHash()));
    }

    /**
     * Proxy for:
     * * v1 `FilesystemInterface::getSize`
     * * v3 `FilesystemAdapter::fileSize`
     *
     * @param IStorage $storage
     * @return int
     * @throws FileNotFoundException
     */
    public function fileSize(IStorage $storage): int
    {
        return $this->filesystem->getSize(self::getPathFromHash($storage->getHash()));
    }

    /**
     * Proxy for:
     * * v1 `FilesystemInterface::listContents`.
     * * v3 `FilesystemAdapter::listContents`
     *
     * @param IStorage $storage
     * @param bool     $recursive
     * @return never-return
     * @throws NotSupportedException
     * @noinspection PhpUnusedParameterInspection
     */
    public function listContents(IStorage $storage, bool $recursive = false)
    {
        throw self::generateDirectorySpecificMethodException();
    }


    /**
     * Proxy for:
     * * v1 `FilesystemInterface::rename`
     * * v3 `FilesystemAdapter::move`
     *
     * @param IStorage $source
     * @param IStorage $destination
     * @return never-return
     * @throws NotSupportedException
     * @noinspection PhpUnusedParameterInspection
     */
    public function move(IStorage $source, IStorage $destination): void
    {
        throw self::generateLocationSpecificMethodException();
    }

    /**
     * Proxy for:
     * * v1 `FilesystemInterface::copy`
     * * v3 `FilesystemAdapter::copy`
     *
     * @param IStorage $source
     * @param IStorage $destination
     * @return never-return
     * @throws NotSupportedException
     * @noinspection PhpUnusedParameterInspection
     */
    public function copy(IStorage $source, IStorage $destination): void
    {
        throw self::generateLocationSpecificMethodException();
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
     * Helper function for determining whether this filesystem uses the S3
     * adapter.
     *
     * @return bool
     */
    public function usesS3Adapter(): bool
    {
        return $this->filesystem instanceof Filesystem
            && $this->filesystem->getAdapter() instanceof S3Adapter;
    }

    /**
     * Generate generic exception for non-supported directory operations
     * (e.g. `createDirectory`, `directoryExists`).
     *
     * @return NotSupportedException
     */
    private static function generateDirectorySpecificMethodException(): NotSupportedException
    {
        throw new NotSupportedException(sprintf('%s does not support directory methods', __CLASS__));
    }

    /**
     * Generate generic exception for non-supported location operations
     * (e.g. `copy`, `move`).
     *
     * @return NotSupportedException
     */
    private static function generateLocationSpecificMethodException(): NotSupportedException
    {
        throw new NotSupportedException(sprintf('%s does not support location methods', __CLASS__));
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
                && $storage->getSize() === $this->filesystem->getSize($previousPath)
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
     * @throws FileNotFoundException
     */
    private function updateStorage(IStorage $storage, string $hash, string $path): void
    {
        if ($storage instanceof Model) {
            $storage
                ->setHash($hash)
                ->setSize($this->filesystem->getSize($path));
        }
    }
}
