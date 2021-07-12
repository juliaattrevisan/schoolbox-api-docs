<?php

/**
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Resource\Attachment\Filesystem;

use Schoolbox\Resource\Attachment\AbstractController;
use Scrub;
use SitePage;

/**
 * Class UploadController
 *
 * @package Schoolbox\Resource\Attachment\Filesystem
 */
class UploadController extends AbstractController
{

    /**
     * @param SitePage $page
     */
    protected function pparse(SitePage $page)
    {
        if (is_null($this->mimeFilter) || $this->mimeFilter == '*/*') {
            $mime = 'null';
        } else {
            $mime = Scrub::text($this->mimeFilter);
        }

        $page->setVar(array(
            'MAX_FILES'      => $this->multi ? '32' : '1',
            'ACCEPTED_FILES' => $mime,
        ));
    }


    /**
     * @return string
     */
    public function getLabel()
    {
        return 'Upload file' . ($this->multi ? 's' : '');
    }


    /**
     * @return string
     */
    public function getRenderMethod()
    {
        return self::RENDER_INLINE;
    }


    /**
     * @return string
     */
    public function getIcon()
    {
        return 'upload';
    }
}
