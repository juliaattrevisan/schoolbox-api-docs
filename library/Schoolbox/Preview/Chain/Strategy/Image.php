<?php
/**
 * Image
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Preview\Chain\Strategy;


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
     * Test the Content-Types
     *
     * @return string
     */
    public function test() {
        if (in_array($this->findContentType(), $this->getContentTypes())) {
            return [
                'type' => 'html-fragment',
                'twig' => [
                    'template' => '@preview/Strategy/Image.twig',
                    'data' => [
                        'src' => $this->getUrl()
                    ]
                ]
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
}