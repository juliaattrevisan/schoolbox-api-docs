<?php
# File		: reportDealer.php
# Written	: James Leckie, 2005
# Description	: report on equipment in the database

require_once( 'include/helpDeskPage.php');
require_once("include/dbManager-mysql.php");
require_once('include/sql.php');

define( '_TITLE', 'Equipment Report');

class reportEquipment 
{
	var $db;
	var $lu;
	var $tpl;
	
	function reportEquipment($filter, $orderby, $groupby)
	{
		
		$this->db = new dbManager(DB_NAME, 1); 
		$this->db->dbConnect();
		$this->page = new HelpDeskPage;
		$this->page->authCheck(3);
		
		$this->filter = $filter;
		$this->groupby = $groupby;
		
		$this->groupByLookup = array(
			'brand' => 'm.brandID',
			'campus' => 'l.campusID',
			'equipmentType' => 'e.equipmentTypeID',); 

		if($groupby)
			$orderbysql = $this->groupByLookup[$groupby].',';

		if($orderby)
			$orderbysql .= $orderby;
		else
			$orderbysql .= 'serial';
		
		
		$this->createSQL($orderbysql);
		$this->outputHTMLReport($groupby);
	}

	function createSQL($orderby)
	{
	
	$sql = "
		SELECT e.equipmentID,
			e.serial,
			e.equipmentTypeID,
			t.description as equipmentType,
			e.modelID,
			m.brandID,
			m.description as model,
			b.description as brand,	
			e.userID,
			o.description as owner,
			u.firstname as firstname,
			u.lastname as surname,
			l.description as location, 
			c.description as campus,
			l.locationID
		FROM	tblEquipment e
			left join
			tblEquipmentType t on e.equipmentTypeID = t.equipmentTypeID
			left join
			tblModel m on e.modelID = m.modelID
			left join
			tblOwner o on o.ownerID = e.ownerID
			left join
			tblBrand b on m.brandID = b.brandID
			left join
			tblLocation l on e.locationID = l.locationID
			left join
			tblCampus c on l.campusID = c.campusID
			left join
			".DB_NAME_PORTAL.".user u on (e.portalUserID <> '') and (u.id = e.portalUserID)
			WHERE ";
		
		foreach($this->filter as $field => $value)
		{
			if($value != '')
			{
				$parms = 1;
				$sql .= $field .'= '.$value.' AND ';
			}
		}
		
		if( $parms == 1) {
			$sql = substr( $sql, 0, strlen( $sql) - 5); 				# trim off trailing ' AND '
		}
		else {										# trim of trailing ' where ';
			$sql = substr( $sql, 0, strlen( $sql) - 6);
		}
		
		if($orderby)
			$sql .= " ORDER BY $orderby";
	
		$this->sql = $sql;
	
			
	}

	function outputCSVReport($sql)
	{
		$query = mysql_query( $sql)
				or die( 'Error querying database for module EquipmentList::getContent() : ' . mysql_error());

		while( $row = mysql_fetch_array( $query)) {
			//create csv file
			
		}
		
		//output headers and file
		
	}

	function dropDown($name, $data, $selected=0)
	{
		$this->_tpl->set_block('tplContent', 'OPTIONS_'.$name, $name);
		
		$this->_tpl->set_var( 'NAME', '(Not Specified)');
		$this->_tpl->set_var( 'VALUE', '');
			
		$this->_tpl->parse( $name, 'OPTIONS_'.$name, true);
		
		foreach($data as $row)
		{
			if($row['id'] == $selected)
				$this->_tpl->set_var( 'SELECTED', 'selected="selected"');
			else
				$this->_tpl->set_var( 'SELECTED', '');
				
			$this->_tpl->set_var( 'NAME', $row['description']);
			$this->_tpl->set_var( 'VALUE', $row['id']);
			
			$this->_tpl->parse( $name, 'OPTIONS_'.$name, true);
		}
		
	}

	function populateFilters()
	{
		$equip = $this->db->dbGetAllRows(SQL_LookupList('EquipmentType'));
		$this->dropDown('EquipmentType', $equip, $this->filter['e.equipmentTypeID']);
		
		$campus = $this->db->dbGetAllRows(SQL_LookupList('Campus'));
		$this->dropDown('Campus', $campus, $this->filter['l.campusID']);
		
		$brand = $this->db->dbGetAllRows(SQL_LookupList('Brand'));
		$this->dropDown('Brand', $brand, $this->filter['m.brandID']);
		
		$this->dropDown('Model', $model);
		
		$groupBy = array(array('id'=>'brand','description'=>'Brand'),
						 array('id'=>'campus','description'=>'Campus'),
						 array('id'=>'equipmentType','description'=>'Type'),);
		
		$this->dropDown('GroupBy', $groupBy, $this->groupby);
	}

	function outputHTMLReport($groupby) 
	{
		
		$this->_tpl = new Module(TPL_DIR);
		$this->_tpl->set_file('tplContent', 'reportEquip.html');
		$this->_tpl->set_block( 'tplContent', 'group', 'groupBlock');
		$this->_tpl->set_block( 'group', 'item', 'itemBlock');
		
		
		switch($groupby)
		{
			case 'equipmentType' :
			$this->_tpl->set_block( 'group', 'type_head', 'type_head_block');
			$this->_tpl->set_block( 'item', 'type_col', 'type_col_block');
			$this->_tpl->set_var('type_head_block', '');	
			$this->_tpl->set_var('type_col_block', '');	
			break;
			
			case 'brand' :
			$this->_tpl->set_block( 'group', 'brand_head', 'brand_head_block');
			$this->_tpl->set_block( 'item', 'brand_col', 'brand_col_block');
			$this->_tpl->set_var('brand_head_block', '');	
			$this->_tpl->set_var('brand_col_block', '');	
			break;
			
			case 'campus' :
			$this->_tpl->set_block( 'group', 'campus_head', 'campus_head_block');
			$this->_tpl->set_block( 'item', 'campus_col', 'campus_col_block');
			$this->_tpl->set_var('campus_head_block', '');	
			$this->_tpl->set_var('campus_col_block', '');	
			break;
		}
		
		$this->populateFilters();
		
		$this->db->dbDoQuery($this->sql);
		
		$lastGroupField = '';
		$count = 0;

		while( $row = $this->db->dbGetNextArray( $query)) {
			
			$row = stripslashes_array($row);
			
			
			if($groupby && $lastGroupField && $row[$groupby] && ($lastGroupField != $row[$groupby]))
			{
				$this->_tpl->set_var( 'GROUP', $lastGroupField." ($count)");
				$this->_tpl->parse( 'groupBlock', 'group', true);
				$count = 0;
				$this->_tpl->set_var( 'itemBlock', '');
			}
			else if(!$lastGroupField)
				$this->_tpl->set_var( 'GROUP', $row[$groupby]);
						
			if( $row[ 'surname'] <> '') {
				$fullname = $row[ 'firstname'] . ' ' . $row[ 'surname'];
			}
			elseif( $row[ 'owner'] == 'MGS') {
				$fullname = $row[ 'owner'];
			}
			else {
				$fullname = '';
			}
			
			$this->_tpl->set_var( 'SERIAL', $row[ 'serial']);
			$this->_tpl->set_var( 'OWNER', $fullname);
			$this->_tpl->set_var( 'TYPE', $row['equipmentType']);
			$this->_tpl->set_var( 'BRAND', $row['brand']);
			$this->_tpl->set_var( 'MODEL', $row['model']);
			$this->_tpl->set_var( 'LOCATION', $row['location']);
			$this->_tpl->set_var( 'CAMPUS', $row['campus']);
			
			$this->_tpl->parse( 'itemBlock', 'item', true);
			
			if($row[$groupby])
				$lastGroupField = $row[$groupby];
			else
				$lastGroupField = 'Other';
				
			$count++;
		}
		
		if(!$groupby)
			$this->_tpl->set_var( 'GROUP', "All ($count)");
		else
			$this->_tpl->set_var( 'GROUP', $lastGroupField." ($count)");
		
		$this->_tpl->parse( 'groupBlock', 'group', true);
		$content = $this->_tpl->parse("finaloutput", "tplContent");
		$this->page->setContent($content);
		$this->page->setTitle("Equipment Report");
		$this->page->printPage();
	}
}

$filter = array (
			'e.equipmentTypeID'=> $_REQUEST[ 'equipmentTypeID'],
			'm.brandID'	=> $_REQUEST[ 'brandID'],
			'e.modelID'	=> $_REQUEST[ 'modelID'],
			'l.campusID' => $_REQUEST['campusID'],
			'locationID' => $_REQUEST[ 'locationID'],);


$page = new reportEquipment($filter, $_GET['orderby'], $_GET['groupBy']);
?>
