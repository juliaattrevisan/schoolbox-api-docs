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
	 * Contextually render thumbnail images
	 *
	 * @param $context
	 * @param $page
	 * @return mixed|void
	 */
	public function renderThumbnail($context, $page) {
		if ($context == 'post') {
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