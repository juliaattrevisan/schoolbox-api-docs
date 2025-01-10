<?php

/**
 * Image
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Preview\Chain\Strategy\External;

class Image extends Base
{
    /**
     * A list of all the file extensions checked here
     *
     * This is a massive pipe assumption that urls end in this set of
     * extensions to be classified as valid images; dynamic images,
     * or any urls which are images and don't match these extensions,
     * are going to be excluded from previewing.
     *
     * @var string
     */
    private const IMAGE_EXTENSIONS = '/\.(jpg|jpeg|pjpeg|gif|png|svg|webp|bmp)$/';

    /**
     * Test the Content-Types
     *
     * @return array|null
     */
    public function test(): ?array
    {
        if (preg_match(self::IMAGE_EXTENSIONS, $this->getUrl())) {
            return [
                'type' => 'image',
                'src' => $this->getUrl(),
            ];
        }

        return $this->testNext();
    }
}
