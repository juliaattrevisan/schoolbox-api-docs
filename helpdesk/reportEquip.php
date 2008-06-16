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
	
	function reportEquipment($filter, $orderby, $orderdir, $groupby)
	{
		
		$this->db = new dbManager(DB_NAME, 1); 
		$this->db->dbConnect();
		$this->page = new HelpDeskPage;
		$this->page->authCheck(3);
		
		$this->filter = $filter;
		$this->groupby = $groupby;
		$this->orderby = $orderby;
		$this->orderdir = $orderdir;
		
		$this->groupByLookup = array(
			'brand' => 'm.brandID',
			'campus' => 'ucampus',
			'equipmentType' => 'e.equipmentTypeID',
			'group'	=> 'u.group_id',
			'model'	=> 'e.modelID',); 

		if($groupby)
			$orderbysql = $this->groupByLookup[$groupby].',';

		if($orderby)
			$orderbysql .= $orderby . ' ' .$orderdir;
		else
			$orderbysql .= 'serial';
		
		if(is_numeric($_GET['delete']))
			$this->delete($_GET['delete']);
		else if(is_numeric($_GET['undelete']))
			$this->undelete($_GET['undelete']);	
		
		$this->createSQL($orderbysql);
		$this->outputHTMLReport($groupby);
	}

	function delete($equipmentID) {
		$params = array('equipmentID' => $equipmentID,
						'active'		=> 0,
						);
		$sql = SQL_equipmentUpdate($params);
		$this->db->dbDoQuery($sql);
	}
	
	function undelete($equipmentID ) { 
			$params = array('equipmentID' => $equipmentID,
						'active'		=> 1,
						);
		$sql = SQL_equipmentUpdate($params);
		$this->db->dbDoQuery($sql);
	}

	function createSQL($orderby)
	{
	
	$sql = "
		SELECT e.equipmentID,
			e.serial,
			e.equipmentTypeID,
			t.description as equipmentType,
			e.dateOfPurchase,
			e.modelID,
			m.brandID,
			m.description as model,
			b.description as brand,	
			e.userID,
			o.description as owner,
			u.id as portalID,
			u.firstname as firstname,
			u.lastname as surname,
			pc.name as ucampus,
			g.name as `group`,
			l.description as location, 
			c.description as campus,
			l.locationID,
			e.active
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
			left join
			".DB_NAME_PORTAL.".userCampus uc on u.id=uc.uid
			left join
			".DB_NAME_PORTAL.".groups g on u.group_id=g.id
			left join
			".DB_NAME_PORTAL.".luCampus pc on uc.campus = pc.id
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
			$sql .= " GROUP BY e.equipmentID ORDER BY $orderby ";
	
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
		
		$campus = $this->db->dbGetAllRows("SELECT id, name as description FROM ".DB_NAME_PORTAL.".luCampus");
		$this->dropDown('Campus', $campus, $this->filter['uc.campus']);
		
		$brand = $this->db->dbGetAllRows(SQL_LookupList('Brand'));
		$this->dropDown('Brand', $brand, $this->filter['m.brandID']);
		
		$model = $this->db->dbGetAllRows(SQL_LookupList('Model'));
		$this->dropDown('Model', $model);
		
		$groupBy = array(array('id'=>'brand','description'=>'Brand'),
						array('id'=>'model','description'=>'Model'),
						 array('id'=>'campus','description'=>'Campus'),
						 array('id'=>'group','description'=>'Group'),
						 array('id'=>'equipmentType','description'=>'Type'),);
		$this->dropDown('GroupBy', $groupBy, $this->groupby);
		
		$orderBy = array(array('id'=>'serial','description'=>'Serial'),
						 array('id'=>'surname','description'=>'Surname'),
						 array('id'=>'brand','description'=>'Brand'),
						 array('id'=>'model','description'=>'Model'),
						 array('id'=>'campus','description'=>'Campus'),
						 array('id'=>'equipmentType','description'=>'Type'),
						 array('id'=>'dateOfPurchase','description'=>'DoP'),);
		$this->dropDown('OrderBy', $orderBy, $this->orderby);
		
		$orderDir = array(array('id'=>'ASC','description'=>'Ascending'),
						  array('id'=>'DESC','description'=>'Descending'),
						  );
		$this->dropDown('OrderDir', $orderDir, $this->orderdir);	
		
		$group = $this->db->dbGetAllRows("SELECT id, name as description FROM ".DB_NAME_PORTAL.".groups");
		$this->dropDown('Group', $group, $this->filter['u.group_id']);
		
		$active = array(array('id'=>1,'description'=>'Yes'),
						array('id'=>'','description'=>'No'),
						);
		$this->dropDown('Active', $active, $this->filter['e.active']);
	}

	function outputHTMLReport($groupby) 
	{
		
		$this->_tpl = new Module(TPL_DIR);
		$this->_tpl->set_file('tplContent', 'reportEquip.html');
		$this->_tpl->set_block( 'tplContent', 'group', 'groupBlock');
		$this->_tpl->set_block( 'group', 'item', 'itemBlock');
		$this->_tpl->set_block( 'item', 'active', 'active_block');
		$this->_tpl->set_block( 'item', 'non_active', 'non_active_block');
		
		
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
			
			if($row['serial'] == "")
				$serial = '<a href="equipmentDetails.php?equipmentID=' . $row[ 'equipmentID'] . '">Unknown</a>';
			else
				$serial = '<a href="equipmentDetails.php?equipmentID=' . $row[ 'equipmentID'] . '">' . $row[ 'serial'] . '</a>';
			
			
			if($row['dateOfPurchase'] != '0000-00-00' && $dop = strtotime($row['dateOfPurchase']) ) 
				$this->_tpl->set_var( 'DOP', date('n/j/Y', $dop));
			else
				$this->_tpl->set_var('DOP', '');

			$result = preg_replace('/(&(un)?delete=[0-9]+)/', '', $_SERVER['REQUEST_URI']);
			$this->_tpl->set_var( 'ACTIVE', $row['active']?'checked="checked"':'');
			$this->_tpl->set_var( 'EQUIPID', $row['equipmentID']);
			$this->_tpl->set_var( 'SERIAL', $serial);
			$this->_tpl->set_var( 'OWNER', '<a href="userDetails.php?portalID=' . $row[ 'portalID'] . '">'.$fullname.'</a>');
			$this->_tpl->set_var( 'TYPE', $row['equipmentType']);
			$this->_tpl->set_var( 'BRAND', $row['brand']);
			$this->_tpl->set_var( 'MODEL', $row['model']);
			$this->_tpl->set_var( 'LOCATION', $row['location']);
			$this->_tpl->set_var( 'CAMPUS', $row['campus']==''?$row['ucampus']:$row['campus']);
			$this->_tpl->set_var( 'CURRENT_URL', $result );
			
			if($row['active']) {
				$this->_tpl->parse('active_block', 'active');
				$this->_tpl->set_var('non_active_block', '');
			}
			else {
				$this->_tpl->parse('non_active_block', 'non_active');
				$this->_tpl->set_var('active_block', '');
			}
			
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
			'uc.campus' => $_REQUEST['campusID'],
			'locationID' => $_REQUEST[ 'locationID'],
			'u.group_id' => $_REQUEST['groupID'], 
			'e.active'	=> $_REQUEST['active'],);


$page = new reportEquipment($filter, $_GET['orderBy'], $_GET['orderDir'], $_GET['groupBy']);
?>
