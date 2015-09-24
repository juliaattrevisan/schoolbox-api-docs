<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Resource\Attachment\Internal;


use Schoolbox\Resource\Attachment\NavigatorController;

class BrowseController extends NavigatorController implements InternalInterface {


    /**
     * @return string
     */
    public function getLabel()
    {
        return 'Resources';
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
        return 'files';
    }

}