<?php

/**
 * PDF
 *
 * @author      Michael Heydon <michael.heydon@schoolbox.education>
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.education/
 */

namespace Schoolbox\Preview\Chain\Strategy\Internal;

class PDF extends Base
{
    /**
     * PDF files
     *
     * @return array|null
     */
    public function test(): ?array
    {
        if ($this->item->getMimetype()->isPDF()) {
            $src = $this->url;
            return [
                'type' => 'pdf',
                'isLocal' => true, // to pass through a ?file parameter to the viewer based on local or external
                'src' => $src
            ];
        }
        return $this->testNext();
    }
}
