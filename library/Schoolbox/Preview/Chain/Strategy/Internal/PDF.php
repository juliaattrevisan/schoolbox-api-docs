<?php

/**
 * PDF
 *
 * @author      Michael Heydon <michael.heydon@schoolbox.education>
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.education/
 */

namespace Schoolbox\Preview\Chain\Strategy\Internal;

use Schoolbox\Resource\File;
use Schoolbox\Resource\Storage;

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
            //set the original url by default, if everything fails, fallback to the original.
            $src = $this->url;

            return [
                'type' => 'pdf',
                'src' => 'https://vagrant.dev.schoolbox.cloud/static/js/pdf/pdfjs/web/viewer.html?file=https://vagrant.dev.schoolbox.cloud' . $src
            ];
        }

        return $this->testNext();
    }
}
