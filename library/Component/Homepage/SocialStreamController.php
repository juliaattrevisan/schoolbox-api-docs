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

		$this->setBlock($templateHandle, 'socialstream_ajax', 'socialstream_ajax_block');
		$this->setBlock($templateHandle, 'has_comments', 'has_comments_block');

		/* Assign homepage id and settings
		 */
		$config = $this->getConfig();

		/* Enforce ints
		 * Use the homepageId that the user selected via Select Alternate Source
		 * or, if that was not set, just use the current homepageId from arg[]
		 */
		if (is_numeric($config['src']) && strlen($config['src'])) {
			$homepageId = intval($config['src']);
		}
		else {
			$homepageId = intval($this->instance->identifier);
		}

		/* Apply limits
		 */
		$limit = intval($config['size']);





		/* Sew thread
		 */
		$thread = new \SocialStream\Thread($homepageId, $limit);
		$tree = $thread->getTree();

		$i = 0;

		/* Set chosen homepageId - this was chosen using Select Alternate Source
		 * or otherwise uses the default arg[0] value
		 */
		$this->setVar(array(
			'HOMEPAGE_ID' => $homepageId
		));


		$this->setFile('socialstream_thread', '/component/homepage/socialstreamThread.html');
		$this->setBlock('socialstream_thread', 'socialstream_post', 'socialstream_post_block');
		$this->setBlock('socialstream_post', 'socialstream_comment', 'socialstream_comment_block');
		$this->setBlock('socialstream_comment', 'socialstream_reply', 'socialstream_reply_block');

		$this->setVar(array(
			'socialstream_post_block' => '',
			'socialstream_comment_block' => '',
			'socialstream_reply_block' => '',
			'has_comments_block' => '',
		));

		/* Traverse everything
		 */
		foreach($tree as $currentPost) {
			$this->setVar(array(
				'SOCIALSTREAM_POST_POST_ID' => Scrub::text($currentPost->getPostId()),
				'SOCIALSTREAM_POST_BODY' => Scrub::text($currentPost->getBody()),
				'SOCIALSTREAM_POST_CREATED_AT' => Scrub::text($currentPost->getCreatedAt()->getShort()),
				'SOCIALSTREAM_POST_USER_EXTERNAL_ID' => Scrub::text($currentPost->getOwnerId()->getExternalId()),
				'SOCIALSTREAM_POST_USER_FULLNAME' => Scrub::text($currentPost->getOwnerId()->getFullname()),
				'ON_OFF' => $i%2 ? 'dark' : '',
			));

			foreach($currentPost->getChildren() as $currentComment) {
				$this->setVar(array(
					'SOCIALSTREAM_COMMENT_POST_ID' => Scrub::text($currentComment->getPostId()),
					'SOCIALSTREAM_COMMENT_BODY' => Scrub::text($currentComment->getBody()),
					'SOCIALSTREAM_COMMENT_CREATED_AT' => Scrub::text($currentComment->getCreatedAt()->getShort()),
					'SOCIALSTREAM_COMMENT_USER_EXTERNAL_ID' => Scrub::text($currentComment->getOwnerId()->getExternalId()),
					'SOCIALSTREAM_COMMENT_USER_FULLNAME' => Scrub::text($currentComment->getOwnerId()->getFullname()),
				));

				foreach($currentComment->getChildren() as $currentReply) {
					$this->setVar(array(
						'SOCIALSTREAM_REPLY_POST_ID' => Scrub::text($currentReply->getPostId()),
						'SOCIALSTREAM_REPLY_BODY' => Scrub::text($currentReply->getBody()),
						'SOCIALSTREAM_REPLY_CREATED_AT' => Scrub::text($currentReply->getCreatedAt()->getShort()),
						'SOCIALSTREAM_REPLY_USER_EXTERNAL_ID' => Scrub::text($currentReply->getOwnerId()->getExternalId()),
						'SOCIALSTREAM_REPLY_USER_FULLNAME' => Scrub::text($currentReply->getOwnerId()->getFullname()),
					));

					$this->parse('socialstream_reply_block', 'socialstream_reply', true);
				}

				/* Clear children after parsing out the parent
				 */
				$this->parse('socialstream_comment_block', 'socialstream_comment', true);
				$this->setVar('socialstream_reply_block', '');
			}

			/* Clear children after parsing out the parent
			 */
			$this->parse('socialstream_post_block', 'socialstream_post', true);
			$this->setVar('socialstream_comment_block', '');
			$this->setVar('socialstream_reply_block', '');

			/* Alternating colours -_-
			 */
			$i++;
		}

		$this->parse('socialstream_ajax_block', 'socialstream_thread');
		print_r($this->sitepage->template->_varVals['socialstream_post_block']);










		/* Determine whether you have access to the input form
		 */
		$newPost = new \SocialStream_Post_String();
		$currentUser = \Schoolbox\UserMapper::getInstance()->findById($_SESSION['id']);
		$authenticator = new \SocialStream\Authenticator($newPost, $this->instance, $currentUser);

		/* If you can neither comment nor collaborate, hide the form
		 * and disable javascript and hide links
		 */
		if (!$authenticator->canComment() && !$authenticator->canCollaborate()) {
			$this->setVar(array(
				'FORM_CLASS' => 'hide',
				'COMMENT_CLASS' => 'hide',
				'has_comments_block' => '',
			));
		}
		else {
			if ($authenticator->canCollaborate()) {
				$this->setVar(array(
					'FORM_CLASS' => '',
					'COMMENT_CLASS' => 'hide',
					'has_comments_block' => '',
				));
			}

			if ($authenticator->canComment()) {
				$this->setVar(array(
					'FORM_CLASS' => '',
					'COMMENT_CLASS' => '',
				));

				$this->parse('has_comments_block', 'has_comments');
			}
		}

		return true;
	}
}