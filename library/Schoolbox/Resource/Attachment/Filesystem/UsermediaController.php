<?php

/**
 * @author      Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Resource\Attachment\Filesystem;

use Schoolbox\Resource\Attachment\AbstractController;
use SitePage;

/**
 * Class UsermediaController
 *
 * An attachment controller for user media (audio/video recordings).
 *
 * @see https://w3c.github.io/mediacapture-main/getusermedia.html
 *
 * @package Schoolbox\Resource\Attachment\Filesystem
 */
class UsermediaController extends AbstractController
{

    /**
     * @param SitePage $page
     */
    protected function pparse(SitePage $page)
    {
        if (is_null($this->mimeFilter) || $this->mimeFilter == '*/*') {
            $mime = 'null';
        } else {
            $mime = "'{$this->mimeFilter}'";
        }

        $page->setVar(
            array(
                'MAX_FILES'      => $this->multi ? '32' : '1',
                'ACCEPTED_FILES' => $mime
            )
        );
    }


    /**
     * @return string
     */
    public function getLabel()
    {
        return 'Recording';
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
        return 'mic';
    }
}
