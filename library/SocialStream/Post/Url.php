<?php
use Schoolbox\File\MimetypeMapper;


/**
 * SocialStream_Post_Url
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
use \Schoolbox\File\Exception as FileException;

class SocialStream_Post_Url extends SocialStream_Post_Abstract {

	/**
	 * Determine whether this should display an icon or something
	 * via the image transform
	 * 
	 * @return string
	 */
	public function determineIcon(): string
    {
        $defaultIcon = 'application-octet-stream.svg';

        /* Load the current icon as set by Admin > Allowed Files because sysadmins can choose their own icons
         * If the filetype is indeterminate, return a default icon rather than nothing
         */
        try {
            return MimetypeMapper::getInstance()->getByFilename($this->getExternalTitle())->getIconFile();
        }
        catch (FileException $e) {
            return $defaultIcon;
        }
	}

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
	 * @param string   $context
	 * @param SitePage $page
	 * @return bool|mixed
	 */
	public function renderThumbnail($context, SitePage $page) {

		/* Determine which context to render blocks
		 * Display a mime icon
		 * Otherwise display thumbnail via image transform
		 */


		$icon = $this->determineIcon();
		if ($context == 'post') {
			$page->setVar(array(
				'SOCIALSTREAM_POST_URL'            => Scrub::text($this->getUrl()),
				'SOCIALSTREAM_POST_EXTERNAL_TITLE' => Scrub::text($this->getExternalTitle()),
				'SOCIALSTREAM_POST_EXTERNAL_BODY'  => Scrub::text($this->getExternalBody()),
			));

			/* Parse metadata
 			 */
			if (strlen($this->getExternalTitle()) || strlen($this->getExternalBody())) {
				$page->parse('socialstream_post_has_metadata_block', 'socialstream_post_has_metadata');
			}

			/* Parse icon
 			 */
			if ($icon) {
				$page->setVar(array(
					'SOCIALSTREAM_POST_ICON' => $icon,
				));
				$page->parse('socialstream_post_has_icon_block', 'socialstream_post_has_icon');
			}
			/* Parse thumbnail if one was successfully found
			 */
			else if ($this->hasThumbnail()) {
				$page->parse('socialstream_post_has_thumbnail_block', 'socialstream_post_has_thumbnail');
			} else {
				// URL with no thumbnail
				$page->parse('socialstream_post_has_link_block', 'socialstream_post_has_link');
			}

			return true;
		}
		else if ($context == 'comment') {
			$page->setVar(array(
				'SOCIALSTREAM_COMMENT_URL'            => Scrub::text($this->getUrl()),
				'SOCIALSTREAM_COMMENT_EXTERNAL_TITLE' => Scrub::text($this->getExternalTitle()),
				'SOCIALSTREAM_COMMENT_EXTERNAL_BODY'  => Scrub::text($this->getExternalBody()),
			));

			/* Parse metadata
			 */
			if (strlen($this->getExternalTitle()) || strlen($this->getExternalBody())) {
				$page->parse('socialstream_comment_has_metadata_block', 'socialstream_comment_has_metadata');
			}

			/* Parse icon
			 */
			if ($icon) {
				$page->setVar(array(
					'SOCIALSTREAM_COMMENT_ICON' => $icon,
				));
				$page->parse('socialstream_comment_has_icon_block', 'socialstream_comment_has_icon');
			}
			/* Parse thumbnail if one was successfully found
			 */
			else if ($this->hasThumbnail()) {
				$page->parse('socialstream_comment_has_thumbnail_block', 'socialstream_comment_has_thumbnail');
			} else {
				// URL with no thumbnail
				$page->parse('socialstream_comment_has_link_block', 'socialstream_comment_has_link');
			}

			return true;
		}
		else if ($context == 'reply') {
			$page->setVar(array(
				'SOCIALSTREAM_REPLY_URL'            => Scrub::text($this->getUrl()),
				'SOCIALSTREAM_REPLY_EXTERNAL_TITLE' => Scrub::text($this->getExternalTitle()),
				'SOCIALSTREAM_REPLY_EXTERNAL_BODY'  => Scrub::text($this->getExternalBody()),
			));

			/* Parse metadata
 			 */
			if (strlen($this->getExternalTitle()) || strlen($this->getExternalBody())) {
				$page->parse('socialstream_reply_has_metadata_block', 'socialstream_reply_has_metadata');
			}

			/* Parse icon
			 */
			if ($icon) {
				$page->setVar(array(
					'SOCIALSTREAM_REPLY_ICON' => $icon,
				));
				$page->parse('socialstream_reply_has_icon_block', 'socialstream_reply_has_icon');
			}
			/* Parse thumbnail if one was successfully found
			 */
			else if ($this->hasThumbnail()) {
				$page->parse('socialstream_reply_has_thumbnail_block', 'socialstream_reply_has_thumbnail');
			} else {
				// URL with no thumbnail
				$page->parse('socialstream_reply_has_link_block', 'socialstream_reply_has_link');
			}

			return true;
		}

		return false;
	}
}
