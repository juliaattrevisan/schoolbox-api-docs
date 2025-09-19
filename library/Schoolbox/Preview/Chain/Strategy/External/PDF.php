<?php

/**
 * PDF
 *
 * @author      Michael Heydon <michael.heydon@schoolbox.education>
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.education/
 */

namespace Schoolbox\Preview\Chain\Strategy\External;

class PDF extends Base
{
    /**
     * PDF files
     *
     * @return array|null
     */
    public function test(): ?array
    {
        if (preg_match('/\.(pdf)$/', $this->getUrl())) {
            $src = $this->url;
            return [
                'type' => 'pdf',
                'src' => 'https://vagrant.dev.schoolbox.cloud/static/js/pdf/pdfjs/web/viewer.html?file=' . $src
            ];
        }
        return $this->testNext();
    }
}
