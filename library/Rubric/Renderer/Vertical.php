<?php
/**
 * Vertical
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Rubric\Renderer;


class Vertical extends Base {

	/**
	 * Set up blocks and stuff
	 */
	protected function setup() {
		$this->getPage()->setFile('rubric', 'submission/inlineGrading/injectables/views/rubric.html');
		$this->getPage()->setBlock('rubric', 'mark_type_rubric', 'mark_type_rubric_block');
	}
}