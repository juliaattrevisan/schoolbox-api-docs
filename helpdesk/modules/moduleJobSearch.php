<?php
# File		: moduleJobSearch.php
# Written	: Nick Doyle, 27/8/2002
# Description	: module to search for job

require_once( 'common.php');
require_once( 'helpDeskPage.php');


class ModuleJobSearch extends HelpDeskPage {
	var $_TPL_NAME = 'jobSearch.html';
	var $_tpl;

	function ModuleJobSearch() {
		$this->_tpl = new Module( TPL_DIR);
		$this->_tpl->set_file( 'tplContent', $this->_TPL_NAME);
	}

	function getContent() {
		$this->populateSelect( 'AssignedUser', 0, '(all)', 'all');
		$this->populateSelect( 'Problem', 0, '(all)', 'all');
		$this->populateSelect( 'Status', 0, '(all)', 'all');

		return $this->_tpl->parse( 'tplContent', 'tplContent');
	}
}

?>
