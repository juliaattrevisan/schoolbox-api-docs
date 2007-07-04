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

define("TEMPLATES", 3);


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

		include("../include/page.php");

		$this->t_body->set_var("CID", $this->cid);

		$template = $this->getTemplate();

		for($x = 1; $x <= TEMPLATES; $x++)
		{
			if($template == $x)
				$this->t_body->set_var("check".$x, "checked");
			else
				$this->t_body->set_var("check".$x, "");
		}

		$this->t_body->pparse("finaloutput", "tplBODY");
	}

	function getComponentList($size)
	{
		// 1 = large
		// 2 = small
		// 0 = all
		//also note that 0 in database represents a blank this should be included with all results

		if($size > 0 )
			$query = "SELECT id, name FROM homepage_comp WHERE size IN($size, 0) ORDER BY size, name";
		else
			$query = "SELECT id, name FROM homepage_comp ORDER BY size, name";

		$this->db->dbGetAllRows($query);

		while($list[] = $this->db->dbGetNextArray() );

		return $list; 

	}
	
	function getTemplate()
	{
			//returns the current template
			$query = "SELECT template FROM homepage_config WHERE cid = '".$this->cid."'";
			$this->db->dbDoQuery($query);
			$temparray = $this->db->dbGetNextArray();
			$template = $temparray['template'];
			return $template;
	}
}
	



if($_REQUEST['cid'] && $_GET['type'] == 'components')
{
	// Check user perms to modify page
	include("modPage.php");
	$mod = new modPage($_REQUEST['cid']);
	$disp = new customise($_REQUEST['cid']);
	
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
	
	// Return true
	print('true');
}
else
{
	//error
}

?>
