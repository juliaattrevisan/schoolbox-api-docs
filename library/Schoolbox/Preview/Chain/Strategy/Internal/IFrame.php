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
     */
    private const CONTENT_TYPES = [
        'html', 'htm', 'txt', 'pdf', 'swf',
    ];

    /**
     * IFrameable content
     *
     * @return array|null
     */
    public function test(): ?array
    {
        if (in_array($this->item->getExtension(), self::CONTENT_TYPES)) {
            return [
                'type' => 'iframe',
                'src' => $this->url,
            ];
        }

        return $this->testNext();
    }
}
