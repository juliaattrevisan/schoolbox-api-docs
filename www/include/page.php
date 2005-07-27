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

//this section handles the drop down menu
if($_SESSION['su'])
	$query = "SELECT sideMenu.id, parent, txt, url, target FROM sideMenu ORDER BY parent, sequence, txt";
else
	$query = "SELECT DISTINCT(sideMenu.id), parent, txt, url, target FROM sideMenu, sideMenuCampus, sideMenuGroup
          WHERE (sideMenu.id = sideMenuCampus.id AND sideMenuCampus.campus IN (".implode(',',$_SESSION['campus']).") )
            AND (sideMenu.id = sideMenuGroup.id AND sideMenuGroup.gid = '".$_SESSION['gid']."')
          ORDER BY parent, sequence, txt";

$db->dbDoQuery($query);

//initialise root of tree
$list[0]['id'] = 0;

//fetch each record and insert into tree
while($menuitem = $db->dbGetNextArray() )
{
	$list = insertChild($list, $menuitem);
}
//output tree with html
$this->t_body->set_var("MCSSMENU", outputMenu($list[0]['children'], 1));

//this section handles the mylinks section in the template 
	
//setup block
$this->t_body->set_block("tplBODY", "mlink_block", "my_links");
$query = "SELECT url, description FROM bookmarks WHERE owner = '".$_SESSION['id']."'";
$db->dbDoQuery($query);
if($db->dbCountRows() > 0)
{
	while($link = $db->dbGetNextArray())
	{
		if(isLocalURL($link['url']) )
		{
			$this->t_body->set_var("MLINK", "<a href=\"/redir.php?url=".$link['url']."\" target=\"_blank\" class=\"one\">".scrubTEXT($link['description'])."</a>");	
		}else{
			$this->t_body->set_var("MLINK", "<a href=\"".$link['url']."\" class=\"one\">".scrubTEXT($link['description'])."</a>");	
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
			$this->t_body->set_var("MGNAME", scrubTEXT($group['name']));
			$this->t_body->set_var("MGID", $group['sid']);	
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
		//$this->t_body->set_var("MLINKSUB", "homepage.php?code=".urlencode($_SESSION['sub_code'][$x]));
		$this->t_body->set_var("MSUBCODE", urlencode($_SESSION['sub_code'][$x]));
		$this->t_body->parse("my_subjects","msubject_block",true); 
	}
	$this->t_body->parse("msubject_section","msubject");
}
else
	$this->t_body->set_var("msubject_section", "");


//this section hanldes the display of the timetable tab
$this->t_body->set_block("tplBODY", "timetable_tab", "tt_tab");

if($_SESSION['timetable'])
{
	$this->t_body->parse("tt_tab","timetable_tab",true); 	
}
else
{
	$this->t_body->set_var("tt_tab", "");
}

//this section handles the display of the webmail tab
// if configuration states mail is available and user has access to local mail account display it
$this->t_body->set_block("tplBODY", "webmail_tab", "wm_tab");

if((MAIL_enable && $_SESSION['local_email']) || (SITE_location == 'wenona' && isStaff($_SESSION['gid'])))
{
	$this->t_body->parse("wm_tab","webmail_tab",true);
}
else
{
	$this->t_body->set_var("wm_tab", "");
}
	
//custom code for each site is performed here	
if(SITE_location == 'wenona')
{
	//provide encoded login details for intranet
	$this->t_body->set_var("ENC_LOGIN", base64_encode(xorEncrypt(INTRANET_encryption_key, $_SESSION['username'].' '.$_SESSION['password'])));
}

//function used to create data structure for drop down menu
function insertChild($list, $element)
{
	for($i=0; $list[$i]; $i++)
	{
		//if item is parent insert item
		if($list[$i]['id'] == $element['parent'])
		{
			$list[$i]['children'][] = $element;
			return $list;
		}
		
		if(is_array($list[$i]['children']))
		{
			$sublist = insertChild($list[$i]['children'], $element);
			//if item was inserted then we can leave else we need to keep searching
			//optimisation step (hopefully should stop linear search)
			if($sublist)
			{
				$list[$i]['children'] = $sublist;
				return $list;
			}
		}
	}
	return false;
}

function outputMenu($list, $level)
{
	if($level == 1)
		$text = "\n<ul class=\"dropDownMenu$level\" id=\"nav\">";
	else
		$text = "<ul class=\"dropDownMenu$level\">";
	
	for($i=0; $list[$i]; $i++)
	{
		$text .= '<li><a href="'.$list[$i]['url'].'">'.scrubTEXT($list[$i]['txt']).'</a>';
		if(is_array($list[$i]['children']))
			$text .= outputMenu($list[$i]['children'], ($level + 1));
		
		$text .= "</li>";
	}
	$text .= "</ul>\n";
	return $text;
}
		
?>