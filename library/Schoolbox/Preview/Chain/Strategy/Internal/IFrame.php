<?php
/**
 * IFrame
 *
 * Load url in an iframe
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Preview\Chain\Strategy\Internal;


class IFrame extends Base {

    /**
     * Content Types for this strategy
     *
     * @var array
     */
    private $contentTypes = [
        'html', 'htm', 'txt', 'csv', 'pdf', 'swf', 'xml',
    ];

    /**
     * Test the Content-Types
     *
     * @return string
     */
    public function test() {
        foreach ($this->getContentTypes() as $currentContentType) {
            if (strstr($this->findContentType(), $currentContentType)) {
                return [
                    'type' => 'html-fragment',
                    'twig' => [
                        'template' => '@preview/Strategy/IFrame.twig',
                        'data' => [
                            'src' => $this->getUrl()
                        ]
                    ]
                ];
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