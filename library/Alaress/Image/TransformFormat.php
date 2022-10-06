<?php

/**
 * @author      James Leckie <james.leckie@schoolbox.com.au>
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Image;

use UnexpectedValueException;

/**
 * This class determines what formats we convert images into for the web
 * It can also tell you what mime type should be sent with specific formats
 * It takes a source image mime, and provides a format and mime string that can be used as the transform
 * @package Alaress\Image
 */
class TransformFormat
{
    /**
     * Mapping of MIME types to image formats.
     */
    private const FORMAT_MAP = [
        'image/heif'    => 'jpg',
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

    /**
     * Mapping of image formats to MIME types.
     */
    private const MIME_MAP = [
        'jpg'  => 'image/jpeg',
        'gif'  => 'image/gif',
        'png'  => 'image/png',
        'webp' => 'image/webp',
        'svg'  => 'image/svg+xml',
    ];

    /**
     * The image format associated with this transform format.
     */
    private string $format;

    /**
     * Constructor
     *
     * @param string $mime A MIME type ("image/jpeg", "image/gif", etc.):
     *                     will be converted to a destination format
     * @throws UnexpectedValueException if `$mime` is not a recognised MIME type
     *                                  for transformations
     */
    public function __construct(string $mime)
    {
        $mime = strtolower($mime);
        if (array_key_exists($mime, self::FORMAT_MAP)) {
            $this->format = self::FORMAT_MAP[$mime];
        } else {
            throw new UnexpectedValueException(
                sprintf('MIME type "%s" is not associated with any image format', $mime)
            );
        }
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
        return self::MIME_MAP[$this->format];
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
