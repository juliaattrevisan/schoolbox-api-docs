<?php
#
# UNUSED - since printTemplate is used
#
# File		: moduleUserSearch.php
# Written	: Nick Doyle, 9/7/2002
# Description	: module to search for a user

require_once( 'common.php');

class ModuleUserSearch {
	var $_TPL_NAME = 'userSearch.html';
	var $_tpl;

		# Constructor takes a reference to an associative array of search parameters (to avoid copying the array object)
	function ModuleJobList() {
		$this->_tpl = new Module( TPL_DIR);
		$this->_tpl->set_file( 'tplContent', $this->_TPL_NAME);
		$this->addJavascript( 'alert( "hui")');
	}

	function getContent() {
		return $this->_tpl->parse( 'tplContent', 'tplContent');
	}
}

?>
