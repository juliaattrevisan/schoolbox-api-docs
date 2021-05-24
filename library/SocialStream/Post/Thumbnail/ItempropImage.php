<?php

/**
 * ItempropImage
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace SocialStream\Post\Thumbnail;

class ItempropImage extends Thumbnail
{

    /**
     * The xpath query
     *
     * @var string
     */
    protected $query = "//*[@itemprop='image']";

    /**
     * These are the attributes that might contain a link
     * to an image you want to retrieve
     *
     * @var array
     */
    protected $contentAttributes = array('src', 'content');
}
