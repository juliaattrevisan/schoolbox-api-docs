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

		$this->setBlock($templateHandle, 'has_comments_javascript', 'has_comments_javascript_block');

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

		/* Set chosen homepageId - this was chosen using Select Alternate Source
		 * or otherwise uses the default arg[0] value
		 */
		$this->setVar(array(
			'HOMEPAGE_ID' => $homepageId
		));

		/* If you can neither comment nor collaborate, hide the form
		 * and disable javascript and hide links
		 */
		$newPost = new \SocialStream_Post_String();
		$currentUser = \Schoolbox\UserMapper::getInstance()->findById($_SESSION['id']);
		$authenticator = new \SocialStream\Authenticator($newPost, $this->instance, $currentUser);

		if (!$authenticator->canComment() && !$authenticator->canCollaborate()) {
			$this->setVar(array(
				'FORM_CLASS' => 'hide',
				'has_comments_javascript_block' => '',
			));
		}
		else {
			if ($authenticator->canCollaborate()) {
				$this->setVar(array(
					'FORM_CLASS' => '',
					'has_comments_javascript_block' => '',
				));
			}

			if ($authenticator->canComment()) {
				$this->setVar(array(
					'FORM_CLASS' => '',
				));

				$this->parse('has_comments_javascript_block', 'has_comments_javascript');
			}
		}

		/* Sew thread
		 */
		$thread = new \SocialStream\Threads($this->instance, $limit, '2013-07-16 18:00:00');
		$output = $thread->generate()->render();

		echo $output;

		return true;
	}
}