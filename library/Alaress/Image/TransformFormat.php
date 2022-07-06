<?php

/**
 * @author      James Leckie <james.leckie@schoolbox.com.au>
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Image;

/**
 * This class determines what formats we convert images into for the web
 * It can also tell you what mime type should be sent with specific formats
 * It takes a source image mime, and provides a format and mime string that can be used as the transform
 * @package Alaress\Image
 */
class TransformFormat
{
    private array $formatMap = [
        'image/heic'    => 'jpg',
        'image/tiff'    => 'jpg',
        'image/jpeg'    => 'jpg',
        'image/bmp'     => 'jpg',
        'image/pjpeg'   => 'jpg',
        'image/gif'     => 'gif',
        'image/png'     => 'png',
        'image/x-png'   => 'png',
        'image/webp'    => 'webp',
        'image/svg+xml' => 'svg',
    ];

    private array $mimeMap = [
        'jpg'  => 'image/jpeg',
        'gif'  => 'image/gif',
        'png'  => 'image/png',
        'webp' => 'image/webp',
        'svg'  => 'image/svg+xml',
    ];


    private string $format;

    /**
     * Provided with a source mime, will convert to a destination format
     * @param string $mime
     */
    public function __construct(string $mime)
    {
        $this->format = $this->formatMap[$mime];
    }

    /**
     * @param string $format
     * @return $this
     */
    public function setFormat(string $format): self
    {
        $this->format = $format;

        return $this;
    }

    /**
     * Gets the destination mime of an image
     * @return string
     */
    public function getMime(): string
    {
        return $this->mimeMap[$this->format];
    }

    /**
     * Gets the destination format of an image
     * @return string
     */
    public function getFormat(): string
    {
        return $this->format;
    }
}
