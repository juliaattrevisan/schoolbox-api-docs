<?php
/**
 * SocialStream_Post_Url
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

class SocialStream_Post_Image extends SocialStream_Post_Abstract {


	/**
	 * Determine whether a thumbnail was successfully found
	 *
	 * @return bool
	 */
	public function hasThumbnail() {
		if (strlen($this->getThumbnail())) {
			return true;
		}

		return false;
	}

	/**
	 * Contextually render thumbnail images
	 *
	 * @param $context
	 * @param $page
	 * @return bool|mixed
	 */
	public function renderThumbnail($context, $page) {

		/* Determine which context to render blocks
		 * Display a mime icon
		 * Otherwise display thumbnail via image transform
		 */
        $returnValue = false;

		if ($context == 'post') {
	        if ($this->hasThumbnail()) {
				$page->parse('socialstream_post_has_image_block', 'socialstream_post_has_image',false);
				$returnValue = true;
			}

			return $returnValue;
		}
		else if ($context == 'comment') {


            if ($this->hasThumbnail()) {
                $page->parse('socialstream_comment_has_image_block', 'socialstream_comment_has_image',false);
                $returnValue = true;
            }

			return $returnValue;
		}
		else if ($context == 'reply') {

            if ($this->hasThumbnail()) {
                $page->parse('socialstream_reply_has_image_block', 'socialstream_reply_has_image',false);
                $returnValue = true;
            }
			return $returnValue;
		}

		return false;
	}
}