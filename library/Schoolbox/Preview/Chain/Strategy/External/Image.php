<?php
/**
 * Image
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Preview\Chain\Strategy\External;


class Image extends Base {

    /**
     * Content Types for this strategy
     *
     * @var array
     */
    private $contentTypes = [
        'image/jpeg',
        'image/pjpeg',
        'image/gif',
        'image/png',
    ];

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
     * @return string
     */
    public function test() {
        if (preg_match($this->getExtensions(), $this->getUrl() )) {
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
     * Set content types
     *
     * @param array $contentTypes
     */
    private function setContentTypes($contentTypes)
    {
        $this->contentTypes = $contentTypes;
    }

    /**
     * Get content types
     *
     * @return array
     */
    private function getContentTypes()
    {
        return $this->contentTypes;
    }

    /**
     * Get content types
     *
     * @return array
     */
    private function getExtensions()
    {
        return $this->extensions;
    }
}