<?php

/**
 * LinkIcon
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace SocialStream\Post\Thumbnail;

class LinkIcon extends Thumbnail
{

    /**
     * The xpath query
     *
     * @var string
     */
    protected $query = "//link[@rel='icon']";

    /**
     * These are the attributes that might contain a link
     * to an image you want to retrieve
     *
     * @var array
     */
    protected $contentAttributes = array('href');
}
