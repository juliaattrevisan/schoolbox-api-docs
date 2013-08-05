<?php
/**
 * SocialStream_Post_Video
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

class SocialStream_Post_Video extends SocialStream_Post_Abstract {

	/**
	 * Interpret youtube's embed link
	 */
//	private function parseVideoUrl() {
//		$matches = array();
//
//		preg_match('/v=([a-zA-Z0-9_]+)&?/', $this->getUrl(), $matches);
//
//		return "//www.youtube.com/embed/{$matches[1]}";
//	}


	private function parseVideoUrl() {
		$matches = array();

		preg_match('/vimeo.com\/([0-9]+)/', $this->getUrl(), $matches);

		return "http://player.vimeo.com/video/{$matches[1]}?color=999999";
	}

	/**
	 * Contextually render thumbnail images
	 *
	 * @param $context
	 * @param $page
	 * @return mixed|void
	 */
	public function renderThumbnail($context, $page) {
		if ($context == 'post') {
			$page->setVar(array(
				'SOCIALSTREAM_POST_URL' => $this->parseVideoUrl(),
			));

			if (strlen($this->getThumbnail())) {
				$page->parse('socialstream_post_has_player_block', 'socialstream_post_has_player');
			}
			else {
				$page->setVar(array(
					'socialstream_post_has_player_block' => ''
				));
			}
		}
		else if ($context == 'comment') {
			$page->setVar(array(
				'SOCIALSTREAM_COMMENT_URL' => $this->parseVideoUrl(),
			));

			if (strlen($this->getThumbnail())) {
				$page->parse('socialstream_comment_has_player_block', 'socialstream_comment_has_player');
			}
			else {
				$page->setVar(array(
					'socialstream_comment_has_player_block' => ''
				));
			}
		}
		else if ($context == 'reply') {
			$page->setVar(array(
				'SOCIALSTREAM_REPLY_URL' => $this->parseVideoUrl(),
			));

			if (strlen($this->getThumbnail())) {
				$page->parse('socialstream_reply_has_player_block', 'socialstream_reply_has_player');
			}
			else {
				$page->setVar(array(
					'socialstream_reply_has_player_block' => ''
				));
			}
		}
	}
}