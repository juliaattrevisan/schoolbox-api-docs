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
    private $extensions = '/\.(jpg|pjpeg|gif|png|jpeg)$/';

    /**
     * Test the Content-Types
     *
     * @return array
     */
    public function test()
    {
        if (preg_match($this->getExtensions(), $this->getUrl())) {
            return [
                'type' => 'image',
                'src' => $this->getUrl(),
            ];
        }

        return $this->testNext();
    }

    /* Getters/Setters
     */

    /**
     * Get content types
     *
     * @return string
     */
    private function getExtensions()
    {
        return $this->extensions;
    }
}
