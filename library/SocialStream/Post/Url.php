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
	 * Determine whether this should display an icon or something
	 * via the image transform
	 * 
	 * @return bool
	 */
	private function determineIcon() {
		if (preg_match('#^icon:(.*)#', $this->getThumbnail(), $matches)) {
			return $matches;
		}

		return false;
	}
	/**
	 * Contextually render thumbnail images
	 *
	 * @param $context
	 * @param $page
	 * @return mixed|void
	 */
	public function renderThumbnail($context, $page) {

		/* Determine which context to render blocks
		 * Display a mime icon
		 * Otherwise display thumbnail via image transform
		 */
		$matches = $this->determineIcon();

		if ($context == 'post') {
			$page->setVar(array(
				'SOCIALSTREAM_POST_URL' => $this->getUrl(),
				'SOCIALSTREAM_POST_EXTERNAL_TITLE' => $this->getExternalTitle(),
				'SOCIALSTREAM_POST_EXTERNAL_BODY' => $this->getExternalBody(),
			));

			if ($matches) {
				$page->setVar(array(
					'SOCIALSTREAM_POST_ICON' => $matches[1],
				));

				$page->parse('socialstream_post_has_icon_block', 'socialstream_post_has_icon');
			}
			else {
				$page->parse('socialstream_post_has_thumbnail_block', 'socialstream_post_has_thumbnail');
			}
		}
		else if ($context == 'comment') {
			$page->setVar(array(
				'SOCIALSTREAM_COMMENT_URL' => $this->getUrl(),
				'SOCIALSTREAM_COMMENT_EXTERNAL_TITLE' => $this->getExternalTitle(),
				'SOCIALSTREAM_COMMENT_EXTERNAL_BODY' => $this->getExternalBody(),
			));

			if ($matches) {
				$page->setVar(array(
					'SOCIALSTREAM_COMMENT_ICON' => $matches[1],
				));

				$page->parse('socialstream_comment_has_icon_block', 'socialstream_comment_has_icon');
			}
			else {
				$page->parse('socialstream_comment_has_thumbnail_block', 'socialstream_comment_has_thumbnail');
			}
		}
		else if ($context == 'reply') {
			$page->setVar(array(
				'SOCIALSTREAM_REPLY_URL' => $this->getUrl(),
				'SOCIALSTREAM_REPLY_EXTERNAL_TITLE' => $this->getExternalTitle(),
				'SOCIALSTREAM_REPLY_EXTERNAL_BODY' => $this->getExternalBody(),
			));

			if ($matches) {
				$page->setVar(array(
					'SOCIALSTREAM_REPLY_ICON' => $matches[1],
				));

				$page->parse('socialstream_reply_has_icon_block', 'socialstream_reply_has_icon');
			}
			else {
				$page->parse('socialstream_reply_has_thumbnail_block', 'socialstream_reply_has_thumbnail');
			}
		}
	}
}