<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Alaress\Mail;

use Swift_IoException;

/**
 * Class AttachmentStream
 *
 * Enables large (~100MB+) attachments without OOM errors.
 */
class AttachmentStream implements \Swift_OutputByteStream{

    /** @var  resource file stream $storageStream */
    protected $storageStream;

    /**
     * AttachmentStream constructor.
     *
     * @param resource file stream $storageStream
     */
    public function __construct($storageStream){
        $this->storageStream = $storageStream;
    }


    /**
     * Reads $length bytes from the stream into a string and moves the pointer
     * through the stream by $length.
     *
     * If less bytes exist than are requested the remaining bytes are given instead.
     * If no bytes are remaining at all, boolean false is returned.
     *
     * @param int $length
     *
     * @throws Swift_IoException
     *
     * @return string|bool
     */
    public function read($length)
    {
        $string = fread($this->storageStream, $length);

        if(false === $string){
            throw new Swift_IoException('Unable to read from stream');
        }
        
        return (0 === strlen($string)) ? false : $string;
    }

    /**
     * Move the internal read pointer to $byteOffset in the stream.
     *
     * @param int $byteOffset
     *
     * @return bool
     */
    public function setReadPointer($byteOffset)
    {
        return 0 === fseek($this->storageStream,$byteOffset);
    }
}