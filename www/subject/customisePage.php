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

define("POSITIONS", 10);
define("TEMPLATES", 3);


class customise
{


	function customise($cid)
	{
		$this->db = new dbManager;
		$this->db->dbConnect();
	
		$this->cid = $cid;

		//var $fonts = array(1 => "Verdana", "Arial", "Helvetica", "Times", "Western", "Courier" );

	}


	function displayTemplatePage()
	{
                $this->t_body = new Template(TPL_directory);
                $this->t_body->set_file("tplBODY", "homepages/customise/template.htm");
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

	function displayLayoutPage()
	{
                $this->t_body = new Template(TPL_directory);
                $this->t_body->set_file("tplBODY", "homepages/customise/layout.htm");
		$this->t_body->set_var("TITLE", HEADING." - Homepage Customisation");

		include("../include/page.php");

		$this->t_body->set_var("CID", $this->cid);
		
		$template = $this->getTemplate();

		if($template == 1)
		{
			$smallcomps = $this->createComponentList(2);
			$largecomps = $this->createComponentList(1);
			$this->t_body->set_var("IMAGE", "70-30.png");
		}
		else if($template == 2)
		{
			$allcomps = $this->createComponentList(0);
			$this->t_body->set_var("IMAGE", "50-50.png");
		}
		else if($template == 3)
		{
			$allcomps = $this->createComponentList(0);
			$this->t_body->set_var("IMAGE", "100-0.png");
		}
		else
			error(0);

	
 		$query = "SELECT component, position, size FROM homepage_comp_location WHERE cid = '".$this->cid."' ORDER BY position";
		$this->db->dbDoQuery($query);
			
		$this->t_body->set_block("tplBODY", "positions", "positions_block"); 
		
		for($x = 1; $x <= POSITIONS; $x++)
		{
			$comploc = $this->db->dbGetNextArray();		

			$this->t_body->set_var("POSITION", "<b>$x</b>");

			if($template == 1 && $x <= (POSITIONS / 2))
				$this->t_body->set_var("POSDROP", $this->createComponentDropDown($largecomps, "pos".$x, $comploc['component'] ));
			else if($template == 1)
				$this->t_body->set_var("POSDROP", $this->createComponentDropDown($smallcomps, "pos".$x, $comploc['component'] ));
			else
				$this->t_body->set_var("POSDROP", $this->createComponentDropDown($allcomps, "pos".$x, $comploc['component'] ));
		
			$this->t_body->set_var("SIZE", "<input name=\"size$x\" type=\"text\" class=\"formfield\" size=\"2\" maxlength=\"2\" 
								value=\"".$comploc['size']."\">");

			$this->t_body->parse("positions_block", "positions", true); 
		}

		$this->t_body->pparse("finaloutput", "tplBODY");
		

	}

	function displayCSSPage()
	{
                $this->t_body = new Template(TPL_directory);
                $this->t_body->set_file("tplBODY", "homepages/customise/display.htm");
		$this->t_body->set_var("TITLE", HEADING." - Homepage Customisation");

		include("../include/page.php");

		$this->t_body->set_var("CID", $this->cid);

		$query = "SELECT id, name FROM homepage_colours";
		$this->db->dbDoQuery($query);
		while($colours[] = $this->db->dbGetNextArray() );

		$query = "SELECT * FROM homepage_config WHERE cid = '".$this->cid."'";
		$this->db->dbDoQuery($query);
		$page = $this->db->dbGetNextArray();

		//$this->t_body->set_var("BACKGROUND_COLOUR", $this->createColourDropDown($colours, "bg_colour", 0));

	
	

		$this->t_body->set_var("HEADING_FONT_COLOUR_LG", $this->createColourDropDown($colours, "head_font_colour_lg", $page['large_head_fontcolour']));
		$this->t_body->set_var("HEADING_BG_COLOUR_LG", $this->createColourDropDown($colours, "head_bg_colour_lg", $page['large_head_bgcolour']));

		$this->t_body->set_var("TEXT_FONT_COLOUR_LG", $this->createColourDropDown($colours, "text_font_colour_lg", $page['large_text_fontcolour']));
		$this->t_body->set_var("TEXT_BG_COLOUR_LG", $this->createColourDropDown($colours, "text_bg_colour_lg", $page['large_text_bgcolour']));

		$this->t_body->set_var("HEADING_FONT_COLOUR_SM", $this->createColourDropDown($colours, "head_font_colour_sm", $page['small_head_fontcolour']));
		$this->t_body->set_var("HEADING_BG_COLOUR_SM", $this->createColourDropDown($colours, "head_bg_colour_sm", $page['small_head_bgcolour']));

		$this->t_body->set_var("TEXT_FONT_COLOUR_SM", $this->createColourDropDown($colours, "text_font_colour_sm", $page['small_text_fontcolour']));
		$this->t_body->set_var("TEXT_BG_COLOUR_SM", $this->createColourDropDown($colours, "text_bg_colour_sm", $page['small_text_bgcolour']));

		$this->t_body->pparse("finaloutput", "tplBODY");

	}

	function createColourDropDown($colours, $name, $selected = 0)
	{
		$html = "<select class=\"formfield\" name=\"$name\">";

		foreach($colours as $colour)
		{
			if($colour['id'] == $selected)
				$html .= "<option value=".$colour['id']." selected>".$colour['name']."</option>";
			else
				$html .= "<option value=".$colour['id'].">".$colour['name']."</option>";
		}
		
		$html .= "</select>";
	
		return $html;

	}

	function createFontDropDown($name, $selected = 0)
	{

		$html = "<select name=\"$name\">";

		foreach($fonts as $font)
		{
			
		}

	}

	function createComponentList($size)
	{
		// 1 = large
		// 2 = small
		// 0 = all
		//also note that 0 in database represents a blank this should be included with all results

		if($size > 0 )
			$query = "SELECT id, name FROM homepage_comp WHERE size IN($size, 0) ORDER BY size, name";
		else
			$query = "SELECT id, name FROM homepage_comp ORDER BY size, name";

		$this->db->dbDoQuery($query);

		while($list[] = $this->db->dbGetNextArray() );

		return $list; 

	}
	
	function createComponentDropDown($list, $name, $selected = 0)
	{

		$html = "<select name=\"$name\">";

		foreach($list as $comp)
		{
			if($comp['id'])
			{
				if($selected == $comp['id'])
					$html .= "<option value=\"".$comp['id']."\" selected>".$comp['name']."</option>";
				else
					$html .= "<option value=\"".$comp['id']."\">".$comp['name']."</option>";
			}		
		}
		
		$html .= "</select>";

		return $html;
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
	



if($_REQUEST['cid'])
{
	//check user perms to modify page

	include("modPage.php");

	$mod = new modPage($_REQUEST['cid']);
	$disp = new customise($_REQUEST['cid']);
	

	if($_GET['layout'] )
	{
		//jump straight to layout
		$disp->displayLayoutPage();		

	}
	else if($_POST['head_bg_colour_lg'])
	{
		$mod->saveDisplayProperties(
			$_POST['head_font_colour_lg'], $_POST['head_bg_colour_lg'],
			$_POST['text_font_colour_lg'], $_POST['text_bg_colour_lg'],
			$_POST['head_font_colour_sm'], $_POST['head_bg_colour_sm'],
			$_POST['text_font_colour_sm'], $_POST['text_bg_colour_sm']
		);
		localRedir("/homepage.php?category=".$_POST['cid']);
	}
	else if($_POST['pos1'])
	{
		//user submitted component layout page lets input there selections
		$mod->deleteComponentPositions();

		for($x=1; $x <= POSITIONS; $x++)
		{
			$posvar = "pos".$x;
			$sizevar = "size".$x; 
			$mod->saveComponentPosition($_POST[$posvar], $x, $_POST[$sizevar]);
		}

		$disp->displayCSSPage();		

	}
	else if($_POST['template'])
	{
		//user just submitted template so save it and show the layout page	
		$mod->savePageTemplate($_POST['template']);
		$disp->displayLayoutPage();		
	}
	else
	{
		//output the template page

		$disp->displayTemplatePage();		
	}
}
else
{
	//error
}

?>
