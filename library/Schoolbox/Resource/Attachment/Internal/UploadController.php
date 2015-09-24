<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Resource\Attachment\Internal;


use Schoolbox\Resource\Attachment\AbstractController;
use SitePage;

class UploadController extends AbstractController implements InternalInterface {

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

        $page->setVar(array(
            'MAX_FILES'      => $this->multi ? '32' : '1',
            'ACCEPTED_FILES' => $mime,
            'SESSION_NAME'   => session_name(),
            'SESSION_ID'     => session_id(),
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