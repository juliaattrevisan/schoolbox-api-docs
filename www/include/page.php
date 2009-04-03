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

$this->t_body->set_var('TEMPLATE_FILE', $this->templateFile);
$this->t_body->set_var('YEAR', date('Y')); 

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
if($db->dbCountRows() > 0){
	while($link = $db->dbGetNextArray()){
		if(isLocalURL($link['url'])){
			$this->t_body->set_var("MLINK", "<a href=\"".$link['url']."\"><span>".scrubTEXT($link['description'])."</span></a>");	
		}else{
			$this->t_body->set_var("MLINK", "<a href=\"/redir.php?url=".urlencode(urlencode($link['url']))."\" target=\"_blank\"><span>".scrubTEXT($link['description'])."</span></a>");
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
	foreach($_SESSION['subjects'] as $subject)
	{
		if(isStaff($_SESSION['gid']))
			$this->t_body->set_var("MSUBJECT", $subject['name']." (".$subject['code'].")");
		else		
			$this->t_body->set_var("MSUBJECT", $subject['name']);

		$this->t_body->set_var("MSUBCODE", urlencode($subject['code']));
		$this->t_body->parse("my_subjects","msubject_block",true); 
	}
	$this->t_body->parse("msubject_section","msubject");
}
else
	$this->t_body->set_var("msubject_section", "");


//my students section
$this->t_body->set_block("tplBODY", "mstudent", "mstudent_section");
$this->t_body->set_block("mstudent", "mstudent_block", "my_students");

if(is_array($_SESSION['children']))
{
	foreach($_SESSION['children'] as $child)
	{
		$query = "SELECT title, firstname, lastname, year FROM user WHERE id = {$child['id']}";
		$user = $db->dbGetSingleRow($query);
		$this->t_body->set_var("MSTUDENT_ID", $child['id']);
		$this->t_body->set_var("MSTUDENT_NAME", formatName($user,'{firstname}'));
		$this->t_body->set_var("MSTUDENT_YEAR", $user['year']);

		$this->t_body->parse("my_students", "mstudent_block", true);
	
	}
	$this->t_body->parse("mstudent_section", "mstudent");
}
else
	$this->t_body->set_var("mstudent_section", "");


//this section hanldes the display of the timetable tab
$this->t_body->set_block('tplBODY', 'timetable_tab', 'tt_tab');
//if the have a timetable or their children have a timetable
if($_SESSION['timetable'] || is_array($_SESSION['children'][0]['subjects']))
	$this->t_body->parse('tt_tab','timetable_tab'); 	
else
	$this->t_body->set_var('tt_tab', '');

//this section handles the display of the webmail tab
// if configuration states mail is available and user has access to local mail account display it
$this->t_body->set_block('tplBODY', 'webmail_tab', 'wm_tab');

if((MAIL_enable && $_SESSION['local_email']) || (SITE_location == 'wenona' && (isStaff($_SESSION['gid']) || isStudent($_SESSION['gid']))) || SITE_location == 'ormond')
{
	$this->t_body->parse('wm_tab','webmail_tab');
	
	if(SITE_location == 'mgs')
	{
		if(isStaff($_SESSION['gid']))
			$this->t_body->set_var("WEBMAIL_LOCATION", "href=\"https://webmail.mgs.vic.edu.au/exchange/\" target=\"_blank\"");
		else
			$this->t_body->set_var("WEBMAIL_LOCATION", "href=\"/mail.php\"");
	}
	else if(SITE_location == 'ormond') 
	{
		$this->t_body->set_var('WEBMAIL_LOCATION', 'href="/mail.php"');
	}
	else if(SITE_location == 'wenona') {
		$this->t_body->set_var('WEBMAIL_LOCATION', 'href="https://webmail.wenona.nsw.edu.au/owa" target="_blank"');
		$this->t_body->set_var('WEBMAIL_TITLE', '<em>W</em>ebmail');

	}
}
else
{
	$this->t_body->set_var('wm_tab', '');
}

//custom code for each site is performed here	
if(SITE_location == 'wenona')
{
	//provide encoded login details for intranet
	$this->t_body->set_var("ENC_LOGIN", base64_encode(xorEncrypt(INTRANET_encryption_key, $_SESSION['username'].' '.$_SESSION['password'].' '.date('Y m d H i'))));

	$this->t_body->set_block('tplBODY', 'portfolio_tab', 'pf_tab');
	$this->t_body->set_block('tplBODY', 'myfiles_tab', 'mf_tab');
	if(isStaff($_SESSION['gid'])) {
		$this->t_body->parse('pf_tab', 'portfolio_tab');
		$this->t_body->parse('mf_tab', 'myfiles_tab');
	}
	else if(isStudent($_SESSION['gid'])) {
		$this->t_body->parse('mf_tab', 'myfiles_tab');
		$this->t_body->set_var('pf_tab', '');
	}
	else {
		$this->t_body->set_var('pf_tab', '');
		$this->t_body->set_var('mf_tab', '');
	}
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
			$list[$i]['children'] = $sublist;
		}
	}
	return $list;
}

function outputMenu($list, $level)
{
		$text = "<ul>\n";
		
		for($i=0; $list[$i]; $i++)
		{
			if($list[$i]['target'])
				$target = " target={$list[$i]['target']}";
			else $target = '';
			
			if(is_array($list[$i]['children'])) {
				$text .= '<li class="parent"><a href="'.$list[$i]['url'].'"'.$target.'><span>'.$list[$i]['txt'].'</span></a>';
				$text .= outputMenu($list[$i]['children'], ($level + 1));
			}
			else 
				$text .= '<li><a href="'.$list[$i]['url'].'"'.$target.'><span>'.$list[$i]['txt'].'</span></a>';
				
			$text .= "</li>\n";
		}
		$text .= "</ul>\n";
		return $text;
}
		
?>