<?php
/**
 * ItempropPhoto
 *
 * First seen at http://www.nigella.com/recipes/view/nursery-fish-pie-128
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace SocialStream\Post\Thumbnail;


class ItempropPhoto extends Thumbnail {

	/**
	 * The xpath query
	 *
	 * @var string
	 */
	protected $query = "//*[@itemprop='photo']";

	/**
	 * These are the attributes that might contain a link
	 * to an image you want to retrieve
	 *
	 * @var array
	 */
	protected $contentAttributes = array('src', 'content');
}