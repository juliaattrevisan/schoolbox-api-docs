<?php
/**
 * Eportfolio_Page_Create
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Eportfolio_Page_Create extends Eportfolio_Page_Abstract {

	/**
	 * The template file to use
	 *
	 * @var string
	 */
	protected $templateFile = 'eportfolio/create.html';

	/**
	 * Initialise classes and collections
	 */
	protected function initialise() {
	}

	/**
	 * Set up blocks and stuff
	 */
	protected function setup() {
		$this->getPage()->setFile(TEMPLATE_content, $this->getTemplateFile());
	}

	/**
	 * Render page
	 */
	protected function render() {
	}
}