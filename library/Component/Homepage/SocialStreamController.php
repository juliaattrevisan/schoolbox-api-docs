<?php
/**
 * Component_Eportfolio_List
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Component_Homepage_SocialStreamController extends Component_Homepage_ControllerAbstract {

	/**
	 * Prepares data for the render function. Business logic.
	 *
	 * @return void
	 */
	public function execute() {
		return true;
	}

	/**
	 * Outputs HTML template defined by config variable 'template'
	 *
	 * @param String $templateHandle
	 * @return bool
	 */
	public function renderTemplate($templateHandle) {
		if (!parent::loadTemplate($templateHandle, 'socialstream')) {
			return false;
		}

		$this->parseConfig();

		$this->setBlock($templateHandle, 'socialstream_post', 'socialstream_post_block');
		$this->setBlock('socialstream_post', 'socialstream_comment', 'socialstream_comment_block');
		$this->setBlock('socialstream_comment', 'socialstream_reply', 'socialstream_reply_block');

		$this->setVar(array(
			'socialstream_post_block' => '',
			'socialstream_comment_block' => '',
			'socialstream_reply_block' => '',
		));

		$homepageId = 10;
		$limit = 3;

		$thread = new \SocialStream\Thread($homepageId, $limit);
		$tree = $thread->getTree();

		/* Traverse everything
		 */
		foreach($tree as $currentPost) {
			foreach($currentPost->getChildren() as $currentComment) {
				foreach($currentComment->getChildren() as $currentReply) {
					$this->setVar(array(
						'SOCIALSTREAM_REPLY_BODY' => $currentReply->getBody()
					));

					$this->parse('socialstream_reply_block', 'socialstream_reply', true);
				}

				$this->setVar(array(
					'SOCIALSTREAM_COMMENT_BODY' => $currentComment->getBody()
				));

				/* Clear children after parsing out the parent
				 */
				$this->parse('socialstream_comment_block', 'socialstream_comment', true);

				$this->setVar('socialstream_reply_block', '');
			}

			$this->setVar(array(
				'SOCIALSTREAM_POST_BODY' => $currentPost->getBody()
			));

			/* Clear children after parsing out the parent
			 */
			$this->parse('socialstream_post_block', 'socialstream_post', true);

			$this->setVar('socialstream_comment_block', '');
		}

		return true;
	}
}