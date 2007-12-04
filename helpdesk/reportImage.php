<?php
# File		: reportDealer.php
# Written	: James Leckie, 2005
# Description	: report on equipment in the database

require_once( 'include/helpDeskPage.php');
require_once("include/dbManager-mysql.php");
require_once('include/sql.php');

define( '_TITLE', 'Image Report');

class reportImage
{
	var $db;
	var $lu;
	var $tpl;
	
	function reportImage($year)
	{
		
		$this->db = new dbManager(DB_NAME, 1); 
		$this->db->dbConnect();
		$this->page = new HelpDeskPage;
		$this->page->authCheck(3);
				
		$this->createSQL($year);
		$this->outputHTMLReport();
	}

	function createSQL($year)
	{
	
		$this->sql = 
		"
		SELECT u.firstname, u.lastname, u.year, h.house, e.serial, b.description as brand, 
			m.description as model, active, image_date.contents as imaged_date, image_by.contents as imaged_by
		FROM ".DB_NAME_PORTAL.".user u 
		LEFT JOIN ".DB_NAME_PORTAL.".house h ON u.house = h.id
		LEFT JOIN tblEquipment e ON u.id = e.portalUserID
		LEFT JOIN tblModel m ON e.modelID = m.modelID
		LEFT JOIN tblBrand b ON m.brandID = b.brandID
		LEFT JOIN tblTemplateContents image_date ON e.equipmentID = image_date.equipmentID AND image_date.fieldName like 'Last imaged date'
		LEFT JOIN tblTemplateContents image_by ON e.equipmentID = image_by.equipmentID AND image_by.fieldName like 'Imaged by'    
		WHERE active = 1 AND equipmentTypeID = 1 AND year = $year 
		ORDER BY year, brand, model, lastname";
	}

	function outputHTMLReport() 
	{
		
		$this->_tpl = new Module(TPL_DIR);
		$this->_tpl->set_file('tplContent', 'reportImage.html');
		$this->_tpl->set_block( 'tplContent', 'item', 'itemBlock');
						
		$this->db->dbDoQuery($this->sql);
		
		while( $row = $this->db->dbGetNextArray()) {
			
			$row = stripslashes_array($row);
									
			$fullname = $row[ 'firstname'] . ' ' . $row[ 'lastname'];
			$this->_tpl->set_var( 'OWNER', $fullname);
			$this->_tpl->set_var( 'YEAR', $row['year']);
			$this->_tpl->set_var( 'HOUSE', $row['house']);
			$this->_tpl->set_var( 'SERIAL', $row[ 'serial']);
			$this->_tpl->set_var( 'BRAND', $row['brand']);
			$this->_tpl->set_var( 'MODEL', $row['model']);
			$this->_tpl->set_var( 'IMAGEBY', $row['imaged_by']);
			$this->_tpl->set_var( 'IMAGEDATE', $row['imaged_date']);
			
			$this->_tpl->parse( 'itemBlock', 'item', true);
		}
		
		$content = $this->_tpl->parse("finaloutput", "tplContent");
		$this->page->setContent($content);
		$this->page->setTitle("Image Report");
		$this->page->printPage();
	}
}


$page = new reportImage($_GET['year']);
?>
