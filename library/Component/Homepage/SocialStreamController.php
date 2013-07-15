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

		return true;
	}
}