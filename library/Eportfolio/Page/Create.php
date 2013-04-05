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
		$this->getPage()->setBlock(TEMPLATE_content, 'page_template_list', 'page_template_list_block');
	}

	/**
	 * Render page
	 */
	protected function render() {
		$this->renderTemplateList();
	}

	private function renderTemplateList() {
		$allTemplates = $this->getEportfolioHandler()->fetchAllTemplates();

		if (is_array($allTemplates) && count($allTemplates)) {
			foreach($allTemplates as $id => $name) {
				$this->getPage()->setVar(array(
					'PAGE_TEMPLATE_ID' => $id,
					'PAGE_TEMPLATE_NAME' => $name,
				));

				$this->getPage()->parse('page_template_list_block', 'page_template_list', true);
			}
		}
		else {
			$this->getPage()->setVar('page_template_list_block', '');
		}
	}
}