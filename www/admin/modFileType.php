<?php
/************************************************************
*                                                           *
*         Copyright (c) 2002-2003 Lektec Consulting         *
*                                                           *
************************************************************/

//Displays filetypes defined in the portal that can be uploaded
require_once("../include/include.php");
require_once("../include/session.php");
require_once("../include/adminonly.php");
require_once("allowedFileType.php");

class modFileType
{
	function modFileType()
	{
		//$this->db = new dbManager;
	}

	function displayFileTypeItem($id)
	{
		$this->t_body = new Template(TPL_directory);
		$this->t_body->set_file("tplBODY", "admin/modFileType.htm");
		$this->t_body->set_var("TITLE", HEADING." - Modify File Type");
		include_once("../include/page.php");	
	
		$this->t_body->set_var("ACTION", "Update");

		$fileType = new allowedFileType();
		
		$item = $fileType->getFileType($id);


		$this->t_body->set_var("DESCRIPTION", scrubTEXT($item['description']));
		$this->t_body->set_var("MIME", scrubTEXT($item['mime']));		
		$this->t_body->set_var("ICONLIST", $this->createIconList($fileType->getIconList(), $item['iconFile']));

		if($item['inline'] == 'true') {
			$this->t_body->set_var('INLINE_TRUE', 'checked="checked"');
			$this->t_body->set_var('INLINE_FALSE', '');
		}
	 	else {
			$this->t_body->set_var('INLINE_FALSE', 'checked="checked"');
			$this->t_body->set_var('INLINE_TRUE', '');
		}
		
		if($item['enable'] == 'true') {
			$this->t_body->set_var('ENABLE_TRUE', 'checked="checked"');
			$this->t_body->set_var('ENABLE_FALSE', '');
		}
	 	else {
			$this->t_body->set_var('ENABLE_FALSE', 'checked="checked"');
			$this->t_body->set_var('ENABLE_TRUE', '');
		}
		
		$this->t_body->set_var('EXTENSIONS', implode(',',$item['extensions']));

		$this->t_body->set_var("IDFIELD", "<input type=\"hidden\" name=\"id\" value=\"".$item['fileTypeID']."\">");		

		$this->t_body->pparse("finaloutput", "tplBODY");

	}

	function displayNewFileTypeItem($parent = 0)
	{
		$this->t_body = new Template(TPL_directory);
		$this->t_body->set_file("tplBODY", "admin/modFileType.htm");
		$this->t_body->set_var("TITLE", HEADING." - Add File Type");
		include_once("../include/page.php");	
		$this->t_body->set_var("ACTION", "Create");
		
		$fileType = new allowedFileType();

		$this->t_body->set_var('DESCRIPTION', '');
		$this->t_body->set_var('MIME', '');		
		$this->t_body->set_var('ICONLIST', $this->createIconList($fileType->getIconList()));
		$this->t_body->set_var('EXTENSIONS', '');
		
		$this->t_body->set_var('INLINE_TRUE', '');
		$this->t_body->set_var('INLINE_FALSE', '');
		$this->t_body->set_var('ENABLE_FALSE', '');
		$this->t_body->set_var('ENABLE_TRUE', '');
		

		$this->t_body->set_var('IDFIELD', '');		
		
		$this->t_body->pparse("finaloutput", "tplBODY");

	}

	function displayFileTypeList()
	{
		$this->t_body = new Template(TPL_directory);
		$this->t_body->set_file("tplBODY", "admin/fileTypes.htm");
		$this->t_body->set_var("TITLE", HEADING." - Manage File Types");
		$fileType = new allowedFileType();
		
		include_once("../include/page.php");
		$this->t_body->set_block('tplBODY', 'file_type', 'file_type_block');

		$fileTypeList = $fileType->getFileTypeList();
		
		foreach($fileTypeList as $fileType) {
			
			$this->t_body->set_var('DESCRIPTION', $fileType['description']);
			$this->t_body->set_var('MIME', $fileType['mime']);
			$this->t_body->set_var('ICON', $fileType['iconFile']);
			$this->t_body->set_var('FILETYPE_ID', $fileType['fileTypeID']);
			
			if($fileType['enable'] == 'true')
				$this->t_body->set_var('STATUS', 'Enabled');
			else
				$this->t_body->set_var('STATUS', 'Disabled');
	
			$this->t_body->parse('file_type_block', 'file_type', true);
		}
		

		$this->t_body->pparse("finaloutput", "tplBODY");
	}

	function createIconList($iconList, $selected = 0)
	{
		//creates the drop down for displaying the menu item target
		
		$html = "<select name=\"icon\">";
		foreach($iconList as $icon){
			if($selected == $icon)
				$html .= "<option value=\"$icon\" selected=\"selected\">$icon</option>";
        	else
				$html .= "<option value=\"$icon\">$icon</option>";
		}		
		$html .= "</select>";
		return $html;
	}
	
		
}

$fileType = new allowedFileType;
$myPage = new modFileType;


if(is_numeric($_GET['delete']))
{
	//delete menu item
	$fileType->deleteFileType($_GET['delete']);
	$myPage->displayFileTypeList();
}
else if($_POST['description'] && $_POST['mime'] && $_POST['icon'] && is_numeric($_POST['id']))
{
	$fileType->updateFileType($_POST['id'], $_POST['description'], $_POST['mime'], $_POST['icon'], $_POST['inline'], $_POST['enable'], $_POST['extensions']);
	$myPage->displayFileTypeList();
}
else if($_POST['text'] && $_POST['url'] && is_numeric($_POST['parent']))
{
	$sideMenu->addFileType($_POST['description'], $_POST['mime'], $_POST['icon'], $_POST['inline'], $_POST['enable'], $_POST['extensions']);
	$myPage->displayFileTypeList();
}

else if(is_numeric($_GET['id']))		
{
	$myPage->displayFileTypeItem($_GET['id']);
}
else if(isset($_GET['add']))
{
	$myPage->displayNewFileTypeItem();
}
else
{
	$myPage->displayFileTypeList();
}
?>
