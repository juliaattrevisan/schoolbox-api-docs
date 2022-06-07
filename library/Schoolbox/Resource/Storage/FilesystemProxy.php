<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Resource\Storage;

use League\Flysystem\FileExistsException;
use League\Flysystem\FileNotFoundException;
use League\Flysystem\FilesystemInterface;
use League\Flysystem\NotSupportedException;

/**
 * A proxy class for providing a `FilesystemInterface`-like facade when dealing
 * with `IStorage` objects, instead of paths.
 *
 * This provides a Flysystem v3-like proxy, even though currently it wraps a
 * v1 Flysystem object. Where the two interfaces differ, v3 will be used.
 *
 * Directory-specific methods are not proxied, because directories are not
 * relevant for `IStorage` objects. All of these methods raise a
 * `NotSupportedException`.
 */
class FilesystemProxy
{
    /**
     * Constructor
     *
     * @param FilesystemInterface $filesystem    base filesystem this class proxies
     * @param HashGenerator       $hashGenerator method for generating hashes for storage files
     */
    public function __construct(
        private FilesystemInterface $filesystem,
        private HashGenerator $hashGenerator,
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
     * @throws FileNotFoundException
     */
    public function delete(IStorage $storage): void
    {
        $this->filesystem->delete(self::getPathFromHash($storage->getHash()));
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
     * Proxy for `FilesystemInterface::listContents`.
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
     * Proxy for `FilesystemInterface::getMetadata`.
     *
     * @param IStorage $storage
     * @return never-return
     * @throws NotSupportedException
     * @noinspection PhpUnusedParameterInspection
     */
    public function getMetadata(IStorage $storage)
    {
        throw new NotSupportedException(sprintf('%s does not support %s', __CLASS__, __METHOD__));
    }

    /**
     * Proxy for `FilesystemInterface::getSize`.
     *
     * @param IStorage $storage
     * @return int
     * @throws FileNotFoundException
     */
    public function getSize(IStorage $storage): int
    {
        return $this->filesystem->getSize(self::getPathFromHash($storage->getHash()));
    }

    /**
     * Proxy for `FilesystemInterface::getMimetype`.
     *
     * @param IStorage $storage
     * @return string
     * @throws FileNotFoundException
     */
    public function getMimetype(IStorage $storage): string
    {
        return $this->filesystem->getMimetype(self::getPathFromHash($storage->getHash()));
    }

    /**
     * Proxy for `FilesystemInterface::getTimestamp`.
     *
     * @param IStorage $storage
     * @return int
     * @throws FileNotFoundException
     */
    public function getTimestamp(IStorage $storage): int
    {
        return $this->filesystem->getTimestamp(self::getPathFromHash($storage->getHash()));
    }

    /**
     * Proxy for `FilesystemInterface::getVisibility`.
     *
     * @param IStorage $storage
     * @return string
     * @throws FileNotFoundException
     */
    public function getVisibility(IStorage $storage): string
    {
        return $this->filesystem->getVisibility(self::getPathFromHash($storage->getHash()));
    }

    /**
     * Proxy for `FilesystemInterface::write`.
     *
     * @param IStorage $storage
     * @param string   $contents
     * @param array    $config
     * @return void
     * @throws FileExistsException if somehow the file exists
     */
    public function write(IStorage $storage, string $contents, array $config = [])
    {
        $fp = fopen('php://temp', 'r+');
        fwrite($fp, $contents);
        rewind($fp);
        $this->writeStream($storage, $fp, $config);
    }

    /**
     * Proxy for `FilesystemInterface::writeStream`.
     *
     * @param IStorage $storage
     * @param resource $resource
     * @param array    $config
     * @return void
     * @throws FileExistsException if somehow the file exists
     */
    public function writeStream(IStorage $storage, $resource, array $config = [])
    {
        $hash = $this->hashGenerator->generateHash($resource);
        // Put it in the filesystem, and also the storage
        // (IStorage doesn't have setHash? Maybe only if $storage is a storage model, then set it?)
        if ($storage instanceof Model) {
            $storage->setHash($hash);
        }

        // TODO is this resource rewound?
        $this->filesystem->writeStream(self::getPathFromHash($hash), $resource, $config);
    }



    /**
     * Returns a relative path for the specified hash
     *
     * @param string $hash
     * @return string
     */
    private static function getPathFromHash(string $hash): string
    {
        return sprintf("%s%s", chunk_split($hash, 5, '/'), $hash);
    }

    /**
     * Generate generic exception for non-supported directory-specific
     * exceptions.
     *
     * @return NotSupportedException
     */
    private static function generateDirectorySpecificMethodException(): NotSupportedException
    {
        throw new NotSupportedException(sprintf('%s does not support directory-specific methods', __CLASS__));
    }
}
