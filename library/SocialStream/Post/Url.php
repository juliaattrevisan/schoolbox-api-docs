<?php
/**
 * SocialStream_Post_Url
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

class SocialStream_Post_Url extends SocialStream_Post_Abstract {

	/**
	 * Use a two-layer caching setup for spawning thumbnail images
	 * For images, thumbnails are obtained via a series of regexes
	 */
	public function spawnThumbnail() {
		$chain = new \SocialStream\Post\Thumbnail\Chain($this);

		/* To prevent a double-curl, remember the place where a thumbnail was
		 * located (this is used as the cached image transform filename hash)
		 */
		if (strlen($chain->getResult())) {
			$this->setThumbnail($chain->getResult());

			/* 	Save the original in the cache location
			 */
			$originalThumbnailCache = new \SocialStream\Cache\Original();
			$originalThumbnailCache->init($this->getThumbnail());
		}
	}
}