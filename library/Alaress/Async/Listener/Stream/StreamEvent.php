<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Async\Listener\Stream;


class StreamEvent
{

    /** @var resource */
    protected $stream;

    /** @var mixed */
    protected $data;

    /**
     * @var array
     */
    protected $meta;


    /**
     * @param resource $stream
     * @param mixed    $data
     */
    public function __construct($stream, $data = null)
    {
        $this->stream = $stream;
        $this->data   = $data;

        if (is_resource($stream)) {
            $this->meta = stream_get_meta_data($stream);
        }
    }


    /**
     * @return resource
     */
    public function getStream()
    {
        return $this->stream;
    }


    /**
     * @return mixed
     */
    public function getData()
    {
        return $this->data;
    }


    /*
     * note there is no isStreamBlocking() method:
     * stream_get_meta_data()['blocked'] always returns true, regardless of actual state
     * @see https://bugs.php.net/bug.php?id=47918
     */


    /**
     * @return string
     */
    public function getStreamType()
    {
        return $this->meta['stream_type'];
    }


    /**
     * @return string
     */
    public function getStreamMode()
    {
        return $this->meta['mode'];
    }


    /**
     * @return int
     */
    public function getStreamUnreadBytes()
    {
        return (int)$this->meta['unread_bytes'];
    }


    /**
     * @return bool
     */
    public function isStreamSeekable()
    {
        return $this->meta['seekable'];
    }


    /**
     * @return bool
     */
    public function isStreamTimedOut()
    {
        return $this->meta['timed_out'];
    }


    /**
     * @return bool
     */
    public function isStreamEnded()
    {
        return $this->meta['eof'];
    }

}
