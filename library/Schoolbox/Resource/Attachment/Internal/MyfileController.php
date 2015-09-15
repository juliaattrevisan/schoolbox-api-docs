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

class MyfileController extends NavigatorController implements InternalInterface {


    /**
     * @param bool $multi
     * @param string $mimeFilter
     */
    public function __construct($multi, $mimeFilter = null)
    {
        // disallow null: cast to '*/*'
        parent::__construct($multi, is_null($mimeFilter) ? '*/*' : $mimeFilter);
    }


    /**
     * @return string
     */
    public function getLabel()
    {
        return 'My Files';
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
