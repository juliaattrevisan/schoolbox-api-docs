<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Resource\Storage\SeekableStream;

use League\Flysystem\AwsS3v3\AwsS3Adapter;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemAdapter;
use Schoolbox\IConfigLoader;
use Schoolbox\Resource\Storage\IStorage;
use Schoolbox\Resource\Storage\S3\Stream as S3Stream;

/**
 * Factory class for building a stream resource which is guaranteed to support
 * seek operations (`fseek`, `rewind`).
 *
 * Not all `\League\Flysystem\FilesystemAdapter::readStream` implementations
 * are guaranteed to return a stream which is readable: notably, the AWS S3
 * implementation does not.
 *
 * @deprecated replaced with a S3 filesystem adapter which always returns a
 *             seekable stream
 *
 * @see https://www.php.net/manual/en/function.fseek.php
 * @see https://www.php.net/manual/en/function.rewind.php
 * @see https://flysystem.thephpleague.com/v1/docs/usage/filesystem-api/#read-files-as-a-stream
 */
class Factory
{
    /**
     * Schoolbox configuration: required for building a stream from AWS
     */
    private IConfigLoader $config;

    /**
     * Constructor
     *
     * @param IConfigLoader $config
     */
    public function __construct(IConfigLoader $config)
    {
        $this->config = $config;
    }

    /**
     * Build a seekable stream given a filesystem and path.
     *
     * @param FilesystemAdapter $filesystem
     * @param string            $path
     * @return resource
     * @throws UnableToReadFile ... if `$path` isn't in `$filesystem`
     */
    public function buildSeekableStream(FilesystemAdapter $filesystem, string $path)
    {
        // getAdapter() isn't on FilesystemAdapter, it only happens to exist
        // on all of its implementations.
        // Therefore check it's an actual filesystem and not a mount manager.
        if ($filesystem instanceof Filesystem && $filesystem->getAdapter() instanceof AwsS3Adapter) {
            return S3Stream::open($path, $this->config);
        }
        return $filesystem->readStream($path);
    }
}
