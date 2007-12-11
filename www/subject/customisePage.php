<?php
/************************************************************
*                                                           *
*         Copyright (c) 2002-2003 Lektec Consulting         *
*                                                           *
************************************************************/

//allow users to customise their homepages

require_once("../include/session.php");
require_once("../include/include.php");
require_once("../include/permission.php");

class customise
{
	function customise($cid)
	{
		$this->db = new dbManager;	
		$this->cid = $cid;
	}

	function displayCustomisePage()
	{
		$this->t_body = new Template(TPL_directory);
		$this->t_body->set_file("tplBODY", "homepages/customise.htm");
		$this->t_body->set_var("TITLE", HEADING." - Homepage Customisation");
		$this->t_body->set_var("CID", $this->cid);
		include("../include/page.php");

		// Template
		$template = $this->getTemplate();
		for($x = 1; $x <= 3; $x++)
		{
			if($template == $x)
				$this->t_body->set_var("templateChecked$x", "checked");
			else
				$this->t_body->set_var("templateChecked$x", "");
		}
		
		// Components
		$this->t_body->set_block("tplBODY", 'componentGroup', 'componentGroupBlock');
		$this->t_body->set_block('componentGroup', 'componentRow', 'componentRowBlock');
		$this->t_body->set_block('componentRow', 'componentCol', 'componentColBlock');
		$this->t_body->set_var('componentGroupName', 'Large Components');
		
		$listLargeComp = $this->getComponentList(1);
		foreach($listLargeComp as $key => $largeComp){
			$this->t_body->set_var('componentID', $largeComp['id']);
			$this->t_body->set_var('componentName', $largeComp['name']);
			if(!empty($largeComp['component']))
				$this->t_body->set_var('componentChecked', 'checked');
			else
				$this->t_body->set_var('componentChecked', '');

			
			$this->t_body->parse('componentColBlock', 'componentCol', true);
			
			if((++$key % 2) == 0 or $largeComp == end($listLargeComp)) {
				$this->t_body->parse('componentRowBlock', 'componentRow', true);
				$this->t_body->set_var('componentColBlock','');
			}
		}
		
		$this->t_body->parse('componentGroupBlock', 'componentGroup',true);
		
		$this->t_body->set_var('componentColBlock','');
		$this->t_body->set_var('componentRowBlock','');
		$this->t_body->set_var('componentGroupName', 'Small Components');
		
		$listSmallComp = $this->getComponentList(2);
		foreach($listSmallComp as $key => $smallComp){
			$this->t_body->set_var('componentID', $smallComp['id']);
			$this->t_body->set_var('componentName', $smallComp['name']);
			if(!empty($smallComp['component']))
				$this->t_body->set_var('componentChecked', 'checked');
			else
				$this->t_body->set_var('componentChecked', '');
			
			$this->t_body->parse('componentColBlock', 'componentCol', true);
			
			if($key % 2 or $smallComp == end($listSmallComp)) {
				$this->t_body->parse('componentRowBlock', 'componentRow', true);
				$this->t_body->set_var('componentColBlock','');
			}
		}
		
		$this->t_body->parse('componentGroupBlock', 'componentGroup',true);
				
		// Colors
		$listColor = $this->getColorList();
		$this->t_body->set_var($listColor);
		
		$this->t_body->pparse("finaloutput", "tplBODY");
	}
	
	// For retrieving selected template
	function getTemplate()
	{
			//returns the current template
			$query = "SELECT template FROM homepage_config WHERE cid = '".$this->cid."'";
			$this->db->dbDoQuery($query);
			$temparray = $this->db->dbGetNextArray();
			$template = $temparray['template'];
			return $template;
	}
	
	// For retrieving components and selected ones
	function getComponentList($size)
	{
		// 1 = large, 2 = small, 0 = all
		if($size > 0 )
			$query = "SELECT id, name, component FROM homepage_comp c LEFT JOIN homepage_comp_location l ON l.component = c.id AND cid = {$this->cid} WHERE c.size IN($size, 0) ORDER BY name";
		else
			$query = "SELECT id, name FROM homepage_comp ORDER BY name";

		return $this->db->dbGetAllRows($query); 
	}
	
	// For retrieving selected colors
	function getColorList()
	{
		$query = "SELECT large_head_fontcolour, large_head_bgcolour, large_text_fontcolour, large_text_bgcolour, small_head_fontcolour, small_head_bgcolour, small_text_fontcolour, small_text_bgcolour FROM homepage_config WHERE cid = '{$this->cid}'";
		return $this->db->dbGetSingleRow($query); 
	}
}
	

// Check user perms to modify page
include("modPage.php");
$mod = new modPage($_REQUEST['cid']);

if($_GET['cid'] && $_GET['type'] == 'components')
{	
	$columns = array($_GET['column1'], $_GET['column2']);
	foreach($columns as $key => $column)
	{
		// Explode the columns
		$key++;
		$components[$key] = explode(',', $column);

		// Update the db
		foreach($components[$key] as $ckey => $component)
		{
			$ckey++;
			$mod->updateComponentPosition($component, $key, $ckey);
		}
	}
}
elseif($_GET['cid'] && $_GET['type'] == 'config' && $_GET['component'])
{	
	$config = $_GET;
	unset($config['cid'], $config['type'], $config['component']);
	$config = serialize($config);
	
	$mod->updateComponentConfig($_GET['component'], $config);
	
	// Send user back to homepage
	localRedir("/homepage.php?category=".$_GET['cid']);
}
elseif($_POST['cid'])
{
	// Update the Template
	$mod->savePageTemplate($_POST['template']);
	
	// Update the Components
	$mod->updateComponents($_POST['components']);

	// Update Colors
	//strip hash
	foreach($_POST['colors'] as $key => $value) {
		$color[$key] = substr($value,1);
	}
	
	$mod->saveDisplayProperties($color);

	// Send user back to homepage
	localRedir("/homepage.php?category=".$_POST['cid']);
}
elseif($_GET['cid'])
{
	// Display modify form
	$disp = new customise($_REQUEST['cid']);
	$disp->displayCustomisePage();
}
else
{
	//error
}

?>
