<?php
/**
 * Eportfolio_Profile_Card
 *
 * This shows the name, image, house and campus information for
 * the dude's eportfolio that you're currently viewing.
 *
 * Originally some kind of hybrid with privateNotes
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Eportfolio_Profile_Card extends Eportfolio_Profile_Abstract {

	/**
	 * Render component
	 *
	 * @return void
	 */
	public function render() {
		$this->getPage()->setBlock(TEMPLATE_content, 'year_level', 'year_level_block');

		$userHandler = new User();
		$profile = $userHandler->getProfile($this->getUserId());

		if ($profile['year'] > 0) {
			$this->getPage()->setVar('YEAR', $profile['year']);
			$this->getPage()->parse('year_level_block', 'year_level');
		}
		else {
			$this->getPage()->setVar('year_level_block', '');
		}

		$this->getPage()->setVar(array(
			'HOUSE' => $profile['house'],
			'CAMPUS' => $profile['campus'],
		));
	}
}
