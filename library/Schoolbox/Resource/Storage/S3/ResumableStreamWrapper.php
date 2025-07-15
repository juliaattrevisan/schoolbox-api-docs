<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Resource\Storage\S3;

use Aws\LruArrayCache;
use Aws\S3\StreamWrapper;
use Aws\S3\S3ClientInterface;
use Aws\CacheInterface;
class ResumableStreamWrapper extends StreamWrapper

{
    /** @var bool */
    private static $useV2Existence = false;

    /** @var int */
    private int $position = 0;

    /** @var int */
    private int $streamStartPosition = 0;

    /** @var int|null  */
    private ?int $fileSize = null;

    /** @var int */
    private int $maxRetries = 3;

    /** @var int  */
    private int $retryAttempt = 0;

    /** @var string  */
    private string $originalPath = '';

    /** @var string  */
    private string $originalMode = '';

    /** @var string  */
    private string $dataBuffer = '';

    public function stream_open($path, $mode, $options, &$opened_path)
    {
        $this->position = 0;
        $this->streamStartPosition = 0;
        $this->retryAttempt = 0;
        $this->originalPath = $path;
        $this->originalMode = $mode;
        $this->fileSize = null;
        $this->dataBuffer = '';

        $mode = rtrim($mode, 'bt');
        $result = parent::stream_open($path, $mode, $options, $opened_path);

        // Get file size for EOF detection
        if ($result && $mode === 'r') {
            $stat = $this->stream_stat();
            $this->fileSize = $stat['size'] ?? null;
        }

        return $result;
    }

    public function stream_read($count)
    {
        if ($this->dataBuffer !== '') {
            $bufferLen = strlen($this->dataBuffer);
            if ($count >= $bufferLen) {
                $result = $this->dataBuffer;
                $this->dataBuffer = '';

                $remaining = $count - $bufferLen;
                if ($remaining > 0) {
                    $additionalData = $this->stream_read($remaining);
                    if ($additionalData !== false) {
                        $result .= $additionalData;
                    }
                }
            } else {
                $result = substr($this->dataBuffer, 0, $count);
                $this->dataBuffer = substr($this->dataBuffer, $count);
            }
            return $result;
        }

        try {
            $data = parent::stream_read($count);

            if ($data !== '') {
                $this->position += strlen($data);
                $this->retryAttempt = 0;
            }

            if ($this->stream_eof() && $this->position < $this->fileSize) {

                if ($data !== '') {
                    $this->dataBuffer = $data;
                }

                if ($this->resumeFromPosition()) {
                    return $this->stream_read($count);
                }
            }

            return $data;

        } catch (\Exception $e) {
            if ($this->resumeFromPosition()) {
                return $this->stream_read($count);
            }

            return false;
        }
    }

    public function stream_seek($offset, $whence = SEEK_SET)
    {
        $targetPosition = $this->position;
        switch ($whence) {
            case SEEK_SET:
                $targetPosition = $offset;
                break;
            case SEEK_CUR:
                $targetPosition = $this->position + $offset;
                break;
            case SEEK_END:
                if ($this->fileSize !== null) {
                    $targetPosition = $this->fileSize + $offset;
                }
                break;
        }

        if ($targetPosition < $this->streamStartPosition) {
            $this->position = $targetPosition;
            return $this->reopenFromPosition($targetPosition);
        }

        $result = parent::stream_seek($offset, $whence);
        $this->position = $targetPosition;
        if ($result) {
            return true;
        }

        return $this->reopenFromPosition($targetPosition);
    }

    public function stream_tell()
    {
        return $this->position;
    }

    private function reopenFromPosition($position): array|bool
    {
        try {
            $this->stream_close();

            $currentContext = stream_context_get_options($this->context);
            $s3Options = $currentContext['s3'] ?? [];

            if ($position > 0) {
                $s3Options['Range'] = "bytes={$position}-";
                $this->streamStartPosition = $position;
            } else {
                unset($s3Options['Range']);
                $this->streamStartPosition = 0;
            }

            $newContext = stream_context_create(['s3' => $s3Options]);
            $this->context = $newContext;

            $opened_path = '';
            return parent::stream_open($this->originalPath, $this->originalMode, 0, $opened_path);

        } catch (\Exception $e) {
            return false;
        }
    }

    private function resumeFromPosition(): bool
    {
        if ($this->retryAttempt >= $this->maxRetries) {
            return false;
        }

        $this->retryAttempt++;

        // Add exponential backoff delay for retries
        if ($this->retryAttempt > 1) {
            $delay = min(2 ** ($this->retryAttempt - 2), 30); // Max 30 seconds
            sleep($delay);
        }

        if ($this->reopenFromPosition($this->position)) {
            return true;
        }

        return false;
    }

    /**
     * Register the resumable 's3://' stream wrapper
     */
    public static function register(
        S3ClientInterface $client,
        $protocol = 's3',
        ?CacheInterface $cache = null,
        $v2Existence = false
    ) {
        self::$useV2Existence = $v2Existence;
        if (in_array($protocol, stream_get_wrappers(), true)) {
            stream_wrapper_unregister($protocol);
        }

        // Register this extended class instead of the base StreamWrapper
        stream_wrapper_register($protocol, self::class, STREAM_IS_URL);
        $default = stream_context_get_options(stream_context_get_default());
        $default[$protocol]['client'] = $client;

        if ($cache) {
            $default[$protocol]['cache'] = $cache;
        } elseif (!isset($default[$protocol]['cache'])) {
            $default[$protocol]['cache'] = new LruArrayCache();
        }

        stream_context_set_default($default);
    }
}
