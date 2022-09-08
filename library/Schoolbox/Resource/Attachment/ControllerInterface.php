<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Resource\Attachment;

use SitePage;

interface ControllerInterface
{
    const RENDER_INLINE = 'inline';
    const RENDER_IFRAME = 'iframe';

    /**
     * @param SitePage $page
     * @return void
     */
    public function parse(SitePage $page);


    /**
     * @return string
     */
    public function getSlug();

    /**
     * @return string
     */
    public function getIcon();

    /**
     * @return string
     */
    public function getLabel();


    /**
     * @return string
     */
    public function getUrl();


    /**
     * @return string
     */
    public function getRenderMethod();
}
