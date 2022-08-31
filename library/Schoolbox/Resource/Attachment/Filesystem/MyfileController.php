<?php

/**
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Resource\Attachment\Filesystem;

use Schoolbox\Resource\Attachment\NavigatorController;

/**
 * Class MyfileController
 *
 * @package Schoolbox\Resource\Attachment\Filesystem
 */
class MyfileController extends NavigatorController
{
    /**
     * @param bool   $multi
     * @param string $mimeFilter
     */
    public function __construct($multi, $mimeFilter = null)
    {
        // disallow null: cast to '*/*'

        /* TODO: his is probably the wrong usage of mimeFilter now
         */
        parent::__construct($multi, is_null($mimeFilter) ? '*/*' : $mimeFilter, null, null, false);
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
