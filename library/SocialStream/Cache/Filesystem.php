<?php
/**
 * Alaress_Image_Cache_Filesystem
 *
 * Load images from some arbitrary path on the filesystem
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
namespace SocialStream\Cache;

class Filesystem extends \Alaress\Image\Cache\BrokenFS {

	/**
	 * Constructor
	 *
	 * Remembers what image you are trying to load from cache
	 *
	 * @param $filename
	 * @param $key
	 */
	public function __construct($filename, $key) {
        parent::__construct(\Cfg::get('stream_dir') . '/socialstream/thumbnails', $filename, $key);
	}
}
