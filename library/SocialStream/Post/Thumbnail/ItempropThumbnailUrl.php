<?php
/**
 * ItempropThumbnailUrl
 *
 * Used in youtube video (this should have been superceded by
 * the video chain, but exists in case there are other urls
 * that use link itemprop='thumbnailUrl')
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace SocialStream\Post\Thumbnail;


class ItempropThumbnailUrl extends Thumbnail {

	/**
	 * The xpath query
	 *
	 * @var string
	 */
	protected $query = "//link[@itemprop='thumbnailUrl']";

	/**
	 * These are the attributes that might contain a link
	 * to an image you want to retrieve
	 *
	 * @var array
	 */
	protected $contentAttributes = array('href');
}