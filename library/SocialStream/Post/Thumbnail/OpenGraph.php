<?php

/**
 * OpenGraph
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace SocialStream\Post\Thumbnail;

class OpenGraph extends Thumbnail
{

    /**
     * The xpath query
     *
     * @var string
     */
    protected $query = "//meta[@property='og:image']";

    /**
     * These are the attributes that might contain a link
     * to an image you want to retrieve
     *
     * @var array
     */
    protected $contentAttributes = array('content');
}
