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
	public function spawnThumbnail($curl) {
		$chain = new \SocialStream\Post\Thumbnail\Chain($curl, $this);

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

	/**
	 * Get the title and description
	 *
	 * @param $curl
	 */
	public function spawnMetadata($curl) {
		$chain = new \SocialStream\Post\Metadata\Chain($curl, $this);

		$this->setExternalTitle($chain->getResult()->getTitle());
		$this->setExternalBody($chain->getResult()->getDescription());
	}

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
		if (strlen($this->getThumbnail())) {

			/* Determine which context to render blocks
			 * Display a mime icon
			 * Otherwise display thumbnail via image transform
			 */
			$matches = $this->determineIcon();

			if ($context == 'post') {
				$page->setVar(array(
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
}