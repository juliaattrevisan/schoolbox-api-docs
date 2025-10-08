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

/**
 * S3 Stream Wrapper with resume capability for handling premature connection termination
 *
 * This class extends AWS S3 StreamWrapper to address a specific issue where Cloudflare
 * terminates connections prematurely when streaming large files from S3, causing EOF
 * to be reached before the entire file has been read.
 *
 * The wrapper implements the following workaround mechanisms:
 * - Automatic retry with exponential backoff (max 3 attempts)
 * - Position tracking to resume from the exact byte where the stream was interrupted
 * - Buffering of partial reads to prevent data loss during interruptions
 * - Range request support to resume downloads from a specific byte position
 *
 * When a premature EOF is detected (stream reports EOF but position < file size),
 * the wrapper automatically reopens the stream with a Range header to continue
 * from the last successfully read position.
 */
class ResumableStreamWrapper extends StreamWrapper
{
    /**
     * The current position of the stream pointer.
     *
     * @var int
     */
    private int $position = 0;

    /**
     * Where should the stream pointer be reopened to, if it is closed
     * prematurely and reopened?
     *
     * @var int
     */
    private int $streamStartPosition = 0;

    /**
     * The size of the stream, as reported on opening.
     *
     * @var int|null
     */
    private ?int $fileSize = null;

    /**
     * How many times should stream reopen be attempted if it closes
     * prematurely?
     *
     * @var int
     */
    private int $maxRetries = 3;

    /**
     * Current reopen attempt.
     *
     * @var int
     */
    private int $retryAttempt = 0;

    /**
     * The original path requested, when opening the stream: used when reopening
     * it.
     *
     * @var string
     */
    private string $originalPath = '';

    /**
     * The original file mode requested, when opening the stream: used when
     * reopening it.
     *
     * @var string
     */
    private string $originalMode = '';

    /**
     * Internal buffer, will be written to when reading and flushed if the
     * previous read closed the stream.
     *
     * @var string
     */
    private string $dataBuffer = '';

    /**
     * @see stream_wrapper_register
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
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

    /**
     * @see stream_wrapper_register
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
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

            $this->triggerError("Stream read failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * @see stream_wrapper_register
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
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

    /**
     * @see stream_wrapper_register
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function stream_tell()
    {
        return $this->position;
    }

    /**
     * Reopen a closed stream from `$position`.
     *
     * @param $position
     * @return array|bool
     */
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
            $this->triggerError("Failed to reopen stream from position {$position}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Resume a stream from `$this->position`.
     *
     * @return bool
     */
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
     * Trigger an error for the stream wrapper
     */
    private function triggerError(string $message): void
    {
        trigger_error($message, E_USER_WARNING);
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
