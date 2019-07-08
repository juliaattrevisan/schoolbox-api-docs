<?php
/**
 * Direct
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace SocialStream\Post\Thumbnail;


class Direct extends Thumbnail {

	/**
	 * Find itemprop='image'
	 *
	 * @return mixed
	 */
	public function fetchThumbnail() {

		/* If it has at least the right extension, download it directly
		 */
        if(($filename = pathinfo(parse_url($this->getUrl(),PHP_URL_PATH),PATHINFO_BASENAME)) && preg_match('#^http#', $this->getUrl()) ) {
            try {
                $file = \Schoolbox\File\MimetypeMapper::getInstance()->getByFilename($filename);
                if($file->isImage()) {
                    return $this->getUrl();
                }
            } catch (\Exception $e) { }
        }

		/* Otherwise get the next class to deal with it
		 */
		if ($this->getNext()) {
			return $this->getNext()->fetchThumbnail();
		}
	}
}
