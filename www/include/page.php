<?php
/************************************************************
*                                                           *
*         Copyright (c) 2003-2004 Lektec Consulting         *
*                                                           *
*	This page fills out the content on the standard portal	*
*	template.  Tabs, drop down menu, groups, subjects are	*
*	all populated from this code. 							*
*   $Id: page.php,v 1.19 2004/04/27 05:16:48 jleckie Exp $						    
*                                                           *
************************************************************/

require_once($_SERVER['DOCUMENT_ROOT']."/include/permission.php");

//recycle any existing db class else continue (should also check it is the write object)
if( isset($this->db) ){
	$db = $this->db;
}
else if(!isset($db))
{
	$db = new dbManager;
	$db->dbConnect();
}

//this section handles the drop down javascript menu
//it sizes the table it sits in (well sorta more like floats in) and use the template engine to build the javascript
define("ITEMHEIGHT", "20");

$this->t_body->set_file("dropmenu", "dynamicmenu.js");
$this->t_body->set_block("dropmenu", "menu_drop_down", "menu_drop_down_block");

if($_SESSION['su'])
	$query = "SELECT sideMenu.id, parent, txt, url, target FROM sideMenu ORDER BY parent, sequence, txt";
else
	$query = "SELECT DISTINCT(sideMenu.id), parent, txt, url, target FROM sideMenu, sideMenuCampus, sideMenuGroup
          WHERE (sideMenu.id = sideMenuCampus.id AND sideMenuCampus.campus IN (".implode(',',$_SESSION['campus']).") )
            AND (sideMenu.id = sideMenuGroup.id AND sideMenuGroup.gid = '".$_SESSION['gid']."')
          ORDER BY parent, sequence, txt";

$db->dbDoQuery($query);
$menucount = 0;

while($menuitem = $db->dbGetNextArray() )
{
        $this->t_body->set_var("JM_ID", "m".$menuitem['id']);
        if($menuitem['parent'] == 0){
                $this->t_body->set_var("JM_PARENT", "");
        		$menucount++;
		}
		else{
                $this->t_body->set_var("JM_PARENT", "m".$menuitem['parent']);
        }
		$this->t_body->set_var("JM_TEXT", $menuitem['txt']);
        $this->t_body->set_var("JM_URL", $menuitem['url']);
        $this->t_body->set_var("JM_TARGET", $menuitem['target']);

        $this->t_body->parse("menu_drop_down_block", "menu_drop_down", true);
}
//first find out how big the menu is so we can size the space for it appropriately
$this->t_body->set_var("MJSHEIGHT", $menucount * ITEMHEIGHT);
$this->t_body->parse("DROPDOWNMENU", "dropmenu");

//this section handles the mylinks section in the template 
	
//setup block
$this->t_body->set_block("tplBODY", "mlink_block", "my_links");
$query = "SELECT url, description FROM bookmarks WHERE owner = '".$_SESSION['id']."'";
$db->dbDoQuery($query);
if($db->dbCountRows() > 0)
{
	while($link = $db->dbGetNextArray())
	{
		$this->t_body->set_var("MDESC", $link['description']);
		if(strncasecmp($link['url'], "http://".SITE_name, 7+strlen(SITE_name)) && strncasecmp($link['url'], "https://".SITE_name, 8+strlen(SITE_name)) )
		{
			$this->t_body->set_var("MLINK", "<a href=\"/redir.php?url=".$link['url']."\" target=\"_blank\" class=\"one\">".$link['description']."</a>");	
		}else{
			$this->t_body->set_var("MLINK", "<a href=\"".$link['url']."\" class=\"one\">".$link['description']."</a>");	
		}
		$this->t_body->parse("my_links", "mlink_block", true);
	}
}
else
	$this->t_body->set_var("my_links", "");

//this section handle mygroups section in the template	
$this->t_body->set_block("tplBODY", "mgroup", "mgroup_section");
$this->t_body->set_block("mgroup", "mgroup_block", "my_groups");

$query = "SELECT category.name, userAccess.sid, userAccess.type 
		  FROM userAccess, category 
		  WHERE userAccess.uid = '".$_SESSION['id']."' 
		  AND userAccess.sid = category.id 
		  AND userAccess.type > 0	
		  AND category.template <> 0
	";
$db->dbDoQuery($query);
if($db->dbCountRows() > 0)
{
	while($group = $db->dbGetNextArray())
	{
			$this->t_body->set_var("MGNAME", $group['name']);
			$this->t_body->set_var("MGLINK", "homepage.php?category=".$group['sid']);	
			$this->t_body->parse("my_groups", "mgroup_block", true);
	}
	$this->t_body->parse("mgroup_section", "mgroup");
}
else
	$this->t_body->set_var("mgroup_section", "");


//this section handles the mysubjects section in the template 
$this->t_body->set_block("tplBODY", "msubject", "msubject_section");
$this->t_body->set_block("msubject", "msubject_block", "my_subjects");

if(is_array($_SESSION['subjects']))
{
	for($x=0;$_SESSION['subjects'][$x]; $x++)
	{
		if(isStaff($_SESSION['gid']))
			$this->t_body->set_var("MSUBJECT", $_SESSION['subjects'][$x]." (".$_SESSION['sub_code'][$x].")");
		else		
			$this->t_body->set_var("MSUBJECT", $_SESSION['subjects'][$x]);
		$this->t_body->set_var("MLINKSUB", "homepage.php?code=".urlencode($_SESSION['sub_code'][$x]));
		$this->t_body->parse("my_subjects","msubject_block",true); 
	}
	$this->t_body->parse("msubject_section","msubject");
}
else
	$this->t_body->set_var("msubject_section", "");


//this section hanldes the display of the timetable tab
$this->t_body->set_block("tplBODY", "timetable_toptab", "tt_toptab");
$this->t_body->set_block("tplBODY", "timetable_tab", "tt_tab");

if($_SESSION['timetable'])
{
	$this->t_body->parse("tt_toptab","timetable_toptab",true); 
	$this->t_body->parse("tt_tab","timetable_tab",true); 	
}
else
{
	$this->t_body->set_var("tt_toptab", "");
	$this->t_body->set_var("tt_tab", "");
}

//this section handles the display of the webmail tab
// if configuration states mail is available and user has access to local mail account display it
$this->t_body->set_block("tplBODY", "webmail_toptab", "wm_toptab");
$this->t_body->set_block("tplBODY", "webmail_tab", "wm_tab");

if((MAIL_enable && $_SESSION['local_email']) || (SITE_location == 'wenona' && isStaff($_SESSION['gid'])))
{
	$this->t_body->parse("wm_toptab","webmail_toptab",true);
	$this->t_body->parse("wm_tab","webmail_tab",true);
}
else
{
	$this->t_body->set_var("wm_toptab", "");
	$this->t_body->set_var("wm_tab", "");
}
	
//custom code for each site is performed here	
if(SITE_location == 'wenona')
{
	//provide encoded login details for intranet
	$this->t_body->set_var("ENC_LOGIN", base64_encode(xorEncrypt('ABCD1234', $_SESSION['username'].' '.$_SESSION['password'])));
}
?>