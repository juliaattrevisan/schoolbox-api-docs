<?php
/**
 * ItempropImage
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace SocialStream\Post\Thumbnail;


class ItempropImage extends Thumbnail {

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