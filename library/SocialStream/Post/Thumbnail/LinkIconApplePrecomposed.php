<?php
/**
 * LinkIconApplePrecomposed
 *
 * First seen at http://www.news.com.au
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace SocialStream\Post\Thumbnail;


class LinkIconApplePrecomposed extends Thumbnail {

	/**
	 * The xpath query
	 *
	 * @var string
	 */
	protected $query = "//link[@rel='apple-touch-icon-precomposed']";

	/**
	 * These are the attributes that might contain a link
	 * to an image you want to retrieve
	 *
	 * @var array
	 */
	protected $contentAttributes = array('href');
}