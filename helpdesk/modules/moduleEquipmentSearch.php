<?php
# File		: moduleEquipmentSearch.php
# Written	: Nick Doyle, 30/7/2002
# Description	: module to search for equipment

require_once( 'common.php');
require_once( 'helpDeskPage.php');

class ModuleEquipmentSearch extends HelpDeskPage {
	var $_TPL_NAME = 'equipmentSearch.html';
	var $_tpl;

		# Constructor takes a referrence to an associative array of search parameters (to avoid copying the array object)
	function ModuleEquipmentSearch() {
		$this->_tpl = new Module( TPL_DIR);
		$this->_tpl->set_file( 'tplContent', $this->_TPL_NAME);
	}

	function getContent() {
		$this->populateSelect( 'EquipmentType', 0);
		$this->populateSelect( 'Brand', 0);
		$this->populateSelect( 'Model', 0);

		return $this->_tpl->parse( 'tplContent', 'tplContent');
	}
}

?>
