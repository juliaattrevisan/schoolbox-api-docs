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
		$chain = new \SocialStream\Post\Player\Chain($this);

		/* If it was interpreted, embed that player
		 */
		if (strlen($chain->getResult())) {
			if ($context == 'post') {
				$page->setVar(array(
					'SOCIALSTREAM_POST_URL' => $chain->getResult(),
					'SOCIALSTREAM_POST_EXTERNAL_TITLE' => $this->getExternalTitle(),
					'SOCIALSTREAM_POST_EXTERNAL_BODY' => $this->getExternalBody(),
				));

				if (strlen($chain->getResult())) {
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
					'SOCIALSTREAM_COMMENT_URL' => $chain->getResult(),
					'SOCIALSTREAM_COMMENT_EXTERNAL_TITLE' => $this->getExternalTitle(),
					'SOCIALSTREAM_COMMENT_EXTERNAL_BODY' => $this->getExternalBody(),
				));

				if (strlen($chain->getResult())) {
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
					'SOCIALSTREAM_REPLY_URL' => $chain->getResult(),
					'SOCIALSTREAM_REPLY_EXTERNAL_TITLE' => $this->getExternalTitle(),
					'SOCIALSTREAM_REPLY_EXTERNAL_BODY' => $this->getExternalBody(),
				));

				if (strlen($chain->getResult())) {
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
}