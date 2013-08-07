<?php
/**
 * OpenGraph
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace SocialStream\Post\Thumbnail;


class OpenGraph extends Thumbnail {

	/**
	 * The xpath query
	 *
	 * @var string
	 */
	protected $query = "/html/head/meta[@property='og:image']";

	/**
	 * These are the attributes that might contain a link
	 * to an image you want to retrieve
	 *
	 * @var array
	 */
	protected $contentAttributes = array('content');
}