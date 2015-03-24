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


class Html extends Base {

    /**
     * Content Types for this strategy
     *
     * @var array
     */
    private $contentTypes = [
        'text/html',
    ];

    /**
     * Test the Content-Types
     *
     * @return string
     */
    public function test() {
        foreach ($this->getContentTypes() as $currentContentType) {
            if (strstr($this->findContentType(), $currentContentType)) {
                return 'Is an html fragment';
            }
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