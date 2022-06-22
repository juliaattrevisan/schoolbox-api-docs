<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Resource\Storage\S3;

use League\Flysystem\Adapter\AbstractAdapter;
use League\Flysystem\Adapter\CanOverwriteFiles;
use League\Flysystem\AwsS3V3\AwsS3V3Adapter as AwsS3Adapter;
use League\Flysystem\Config;
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
class Adapter extends AbstractAdapter implements CanOverwriteFiles
{
    /**
     * Base AWS S3 adapter: most adapter methods proxy this.
     *
     * Expects that its S3 client's `registerStreamWrapper` method has been
     * called.
     */
    private AwsS3Adapter $baseAdapter;

    /**
     * Constructor
     *
     * @param AwsS3Adapter $baseAdapter
     */
    public function __construct(AwsS3Adapter $baseAdapter)
    {
        $this->baseAdapter = $baseAdapter;
    }

    /**
     * Overrides the S3 adapter's implementation to use a direct stream open.
     *
     * This stream is seekable, but the returned array doesn't contain all of
     * the metadata that the S3 adapter would return. That doesn't matter for
     * a file's `readStream` method, as it returns only the stream.
     *
     * @param string $path
     * @return array|bool
     */
    public function readStream($path)
    {
        $context = stream_context_create([
            's3' => ['seekable' => true],
        ]);

        // Return seekable stream
        $bucket = $this->baseAdapter->getBucket();
        $prefix = FilesystemFactory::PREFIX_STORAGE;
        $stream = fopen(
            "s3://{$bucket}/{$prefix}{$path}",
            "rb",
            false,
            $context,
        );
        if ($stream === false) {
            return false;
        }
        return [
            'type'   => 'file',
            'path'   => $this->applyPathPrefix($path),
            'stream' => $stream
        ];
    }

    /**
     * All methods below are simply delegated to the base adapter.
     */

    /**
     * @inheritdoc
     */
    public function write($path, $contents, Config $config)
    {
        return $this->baseAdapter->write($path, $contents, $config);
    }

    /**
     * @inheritdoc
     */
    public function writeStream($path, $resource, Config $config)
    {
        return $this->baseAdapter->writeStream($path, $resource, $config);
    }

    /**
     * @inheritdoc
     */
    public function update($path, $contents, Config $config)
    {
        return $this->baseAdapter->update($path, $contents, $config);
    }

    /**
     * @inheritdoc
     */
    public function updateStream($path, $resource, Config $config)
    {
        return $this->baseAdapter->updateStream($path, $resource, $config);
    }

    /**
     * @inheritdoc
     */
    public function rename($path, $newpath)
    {
        return $this->baseAdapter->rename($path, $newpath);
    }

    /**
     * @inheritdoc
     */
    public function copy($path, $newpath)
    {
        return $this->baseAdapter->copy($path, $newpath);
    }

    /**
     * @inheritdoc
     */
    public function delete($path)
    {
        return $this->baseAdapter->delete($path);
    }

    /**
     * @inheritdoc
     */
    public function deleteDir($dirname)
    {
        return $this->baseAdapter->deleteDir($dirname);
    }

    /**
     * @inheritdoc
     */
    public function createDir($dirname, Config $config)
    {
        return $this->baseAdapter->createDir($dirname, $config);
    }

    /**
     * @inheritdoc
     */
    public function setVisibility($path, $visibility)
    {
        return $this->baseAdapter->setVisibility($path, $visibility);
    }

    /**
     * @inheritdoc
     */
    public function has($path)
    {
        return $this->baseAdapter->has($path);
    }

    /**
     * @inheritdoc
     */
    public function read($path)
    {
        return $this->baseAdapter->read($path);
    }

    /**
     * @inheritdoc
     */
    public function listContents($directory = '', $recursive = false)
    {
        return $this->baseAdapter->listContents($directory, $recursive);
    }

    /**
     * @inheritdoc
     */
    public function getMetadata($path)
    {
        return $this->baseAdapter->getMetadata($path);
    }

    /**
     * @inheritdoc
     */
    public function getSize($path)
    {
        return $this->baseAdapter->getSize($path);
    }

    /**
     * @inheritdoc
     */
    public function getMimetype($path)
    {
        return $this->baseAdapter->getMimetype($path);
    }

    /**
     * @inheritdoc
     */
    public function getTimestamp($path)
    {
        return $this->baseAdapter->getTimestamp($path);
    }

    /**
     * @inheritdoc
     */
    public function getVisibility($path)
    {
        return $this->baseAdapter->getVisibility($path);
    }
}
