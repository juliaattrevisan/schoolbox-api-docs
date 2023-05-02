<?php

/**
 * IFrame
 *
 * Load url in an iframe
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Preview\Chain\Strategy\Internal;

class IFrame extends Base
{

    /**
     * Content Types for this strategy
     *
     * @var array
     */
    private $contentTypes = [
        'html', 'htm', 'txt', 'swf',
    ];

    /**
     * IFrameable content
     *
     * @return string
     */
    public function test()
    {
        if (
            $this->getItem()
            && $this->getItem()->getMimetype()->getInline()
            && in_array($this->getItem()->getExtension(), $this->getContentTypes())
        ) {
            return [
                'type' => 'iframe',
                'src' => $this->getUrl(),
            ];
        }

        // Handle display of an inline HTML response to a due work
        if (preg_match('`/learning/assessments/\\d+/\\d+/responses/\\d+/files/`', $this->url)) {
            return [
                'type' => 'iframe',
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
}
