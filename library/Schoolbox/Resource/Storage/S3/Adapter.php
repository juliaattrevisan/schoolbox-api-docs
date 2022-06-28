<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Resource\Storage\S3;

use League\Flysystem\AwsS3V3\AwsS3V3Adapter as AwsS3Adapter;
use League\Flysystem\Config;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\UnableToReadFile;
use Schoolbox\Resource\FilesystemFactory;

/**
 * Wrapper around the Flysystem AWS S3 adapter.
 *
 * This wrapper overrides `readStream` to always return a seekable stream,
 * whilst leaving the rest of the S3 adapter untouched.
 *
 * Previous commentary on this issue:
 *
 * Flysystem is unable to chunk a file from storage which makes streaming of
 * videos from S3 impossible. This makes video playback from S3 a non-starter as
 * you have to wait for the entire video to download to the server before it can
 * start being streamed to the user.
 *
 * This class is a kludge to connect to S3 directly and download the file using
 * their native async methods with a progress callback that streams it out to
 * the user as we receive it.
 */
class Adapter implements FilesystemAdapter
{
    /**
     * Constructor
     *
     * @param AwsS3Adapter $baseAdapter Base AWS S3 adapter: most adapter methods proxy this.
     *                                  Expects that its S3 client's `registerStreamWrapper` method has been called.
     * @param string       $bucket      The S3 bucket to read from.
     *                                  Must be passed here because `AwsS3Adapter` instance's bucket is no longer
     *                                  accessible outside the instance.
     */
    public function __construct(
        private AwsS3Adapter $baseAdapter,
        private string $bucket,
    ) {
    }

    /**
     * Overrides the S3 adapter's implementation to use a direct stream open.
     *
     * This stream is seekable, but the returned array doesn't contain all of
     * the metadata that the S3 adapter would return. That doesn't matter for
     * a file's `readStream` method, as it returns only the stream.
     *
     * @param string $path
     * @return resource
     */
    public function readStream(string $path)
    {
        $context = stream_context_create([
            's3' => ['seekable' => true],
        ]);

        // Return seekable stream
        error_clear_last();
        $prefix = FilesystemFactory::PREFIX_STORAGE;
        $stream = @fopen(
            sprintf("s3://%s/%s%s", $this->bucket, $prefix, $path),
            "rb",
            false,
            $context,
        );
        if ($stream === false) {
            throw UnableToReadFile::fromLocation($path, error_get_last()['message'] ?? '');
        }
        return $stream;
    }

    /**
     * All methods below are simply delegated to the base adapter.
     */

    /**
     * @inheritdoc
     */
    public function fileExists(string $path): bool
    {
        return $this->baseAdapter->fileExists($path);
    }

    /**
     * @inheritdoc
     */
    public function directoryExists(string $path): bool
    {
        return $this->baseAdapter->directoryExists($path);
    }

    /**
     * @inheritdoc
     */
    public function write(string $path, string $contents, Config $config): void
    {
        $this->baseAdapter->write($path, $contents, $config);
    }

    /**
     * @inheritdoc
     */
    public function writeStream(string $path, $contents, Config $config): void
    {
        $this->baseAdapter->writeStream($path, $contents, $config);
    }

    /**
     * @inheritdoc
     */
    public function read(string $path): string
    {
        return $this->baseAdapter->read($path);
    }

    /**
     * @inheritdoc
     */
    public function delete(string $path): void
    {
        $this->baseAdapter->delete($path);
    }

    /**
     * @inheritdoc
     */
    public function deleteDirectory(string $path): void
    {
        $this->baseAdapter->deleteDirectory($path);
    }

    /**
     * @inheritdoc
     */
    public function createDirectory(string $path, Config $config): void
    {
        $this->baseAdapter->createDirectory($path, $config);
    }

    /**
     * @inheritdoc
     */
    public function setVisibility(string $path, string $visibility): void
    {
        $this->baseAdapter->setVisibility($path, $visibility);
    }

    /**
     * @inheritdoc
     */
    public function visibility(string $path): FileAttributes
    {
        return $this->baseAdapter->visibility($path);
    }

    /**
     * @inheritdoc
     */
    public function mimeType(string $path): FileAttributes
    {
        return $this->baseAdapter->mimeType($path);
    }

    /**
     * @inheritdoc
     */
    public function lastModified(string $path): FileAttributes
    {
        return $this->baseAdapter->lastModified($path);
    }

    /**
     * @inheritdoc
     */
    public function fileSize(string $path): FileAttributes
    {
        return $this->baseAdapter->fileSize($path);
    }

    /**
     * @inheritdoc
     */
    public function listContents(string $path, bool $deep): iterable
    {
        return $this->baseAdapter->listContents($path, $deep);
    }

    /**
     * @inheritdoc
     */
    public function move(string $source, string $destination, Config $config): void
    {
        $this->baseAdapter->move($source, $destination, $config);
    }

    /**
     * @inheritdoc
     */
    public function copy(string $source, string $destination, Config $config): void
    {
        $this->baseAdapter->copy($source, $destination, $config);
    }
}
