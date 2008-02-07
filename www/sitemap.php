<?php

require_once('include/session.php');
require_once('include/include.php');
require_once('include/permission.php');

$db = new dbManager;
$t_body = new Template(TPL_directory);


class OutPut
{
	function OutPut($category, $verbose)
	{
		$this->category = $category;
		$this->verbose = $verbose;
		
		$this->t_body = new Template(TPL_directory);
		$this->t_body->set_file('tplBODY', 'sitemap.htm');

		$this->t_body->set_var("TITLE", HEADING." - Sitemap");
		$this->t_body->set_var("CID", $category);
		

		//database handle
		$this->db = new dbManager;

		include_once("include/page.php");	

	}

	function showPage()
	{
		$this->t_body->pparse("finaloutput", "tplBODY");
	}
	
	function outputCategories($parent, $block)
	{

		$query = "SELECT 
					id, 
					name,
					code,
				  (SELECT count(*) FROM category WHERE parent = c.id) + (SELECT count(*) FROM file WHERE category = c.id) as children
				  FROM category c
				  WHERE parent = $parent ORDER BY name";	
		$catList = $this->db->dbGetAllRows($query);

		if (is_array($catList)) {
			foreach($catList as $category) {		
				$output = array ('CAT_ID' 		 => $category['id'],
								 'CAT_NAME'      => $category['name'] . (!empty($category['code']) ? " (" . $category['code'] . ")":""),
								 'CAT_OPENCLOSE' => $category['children']>0?"<img src=\"/images/icons/sitemap-expand.gif\" alt=\"Expand\" />":"",
								 
								);
								
				$this->t_body->set_var($output);
				if ($block == 'nav')
					$this->t_body->parse('category_block', 'category', true);
				else
					$this->t_body->parse('category_window_block', 'category_window', true);
			}
		} else {
		
				if ($block == 'nav')
					$this->t_body->set_var('category_block', '');
				else
					$this->t_body->set_var('category_window_block', '');
		}
		
	
	}
	
	function outputFiles($parent)
	{
		
		$query = "SELECT file.id, realname, '' as url, file_size, file.description, iconFile, allowedFileTypes.description as mime_description, inline
				  FROM file 
				  LEFT JOIN allowedFileTypes ON file.mime_type = allowedFileTypes.mime
				  WHERE file.category = $parent AND deleted = '0'
				  
				  UNION SELECT link.id, '', link.url, '', link.description, 'http-url.png', link.comment, ''
				  FROM link
				  WHERE link.category = $parent
				  
				  ORDER BY description";
		$fileList = $this->db->dbGetAllRows($query);
		
		if (is_array($fileList)) {
			foreach ($fileList as $file) {
				$output = array ('FILE_ID' 	 => $file['id'],
								 'FILE_NAME' => scrubText($file['description']),
								 'FILE_ICON' => !is_null($file['iconFile']) ? "/images/mime/".$file['iconFile'] : "/images/mime/application-x-none.png",
								 'FILE_ALT'  => !is_null($file['mime_description']) ? $file['mime_description'] : "File",
	
								);

				$filename = FILE_directory . "/" . $file['id'] . ".dat";

				// set file size
				if($file['realname'] != "")
				{
					if($file['file_size'])
						$filesize = $file['file_size'];
					else
						$filesize = @filesize($filename);
					
					if($filesize == FALSE) //if we cannot find the file (something is wrong dont link to it)
					{
						$this->t_body->set_var("FILE_SIZE", "");
						$file['realname'] = "";	
					}
					else
						$this->t_body->set_var("FILE_SIZE", bytesToString($filesize));
				}
				else
					$this->t_body->set_var("FILE_SIZE", "");	
					

				if($file['realname'] != "")
				{
					//if inline open in new window, if not give user a save dialog box
					if($file['inline'] == 'true')
						$this->t_body->set_var("FILE_URL", "<a href=\"/sendframe.php?id=".$file['id']."\" onclick=\"window.open(this.href, '_blank',''); return false;\">");
					else
						$this->t_body->set_var("FILE_URL", "<a href=\"/send.php?id=".$file['id']."&save=1\">");
						
					$this->t_body->set_var('FILE_URL_END', '</a>');
				
				} else if(isLocalURL($file['url']))
				{
					$this->t_body->set_var("FILE_URL", "<a href=\"" . $file['url'] ."\" onclick=\"window.open(this.href, '_self',''); return false;\">");
					$this->t_body->set_var('FILE_URL_END', '</a>');
				}
				else if ($file['url'] != "")
				{
					$this->t_body->set_var("FILE_URL", "<a href=\"/redir.php?url=" . $file['url'] ."\" onclick=\"window.open(this.href, '_blank',''); return false;\">");
					$this->t_body->set_var('FILE_URL_END', '</a>');
				}
				
				else
				{
					$this->t_body->set_var('FILE_URL', '');
					$this->t_body->set_var('FILE_URL_END', '');
				}										
								
				$this->t_body->set_var($output);
				$this->t_body->parse('file_block', 'file', true);
	
			}
		} else
			$this->t_body->set_var('file_block', '');	
	
	}


	function outputNav($parent) {

		$this->t_body->set_file('blockBODY', 'sitemap_block.htm');
		$this->t_body->set_block('blockBODY', 'category', 'category_block');
		$this->t_body->set_block('blockBODY', 'file', 'file_block');
		
		$this->t_body->set_block('blockBODY', 'category_window', 'category_window_block');
		
		$this->t_body->set_var('category_window_block', '');
		$this->t_body->set_var('file_block', '');
		
		$this->outputCategories($parent, 'nav');
		
		$this->t_body->pparse("finaloutput", "blockBODY");
		
	}
	
	function outputWindow($parent) {
	
		$this->t_body->set_file('blockBODY', 'sitemap_block.htm');
		$this->t_body->set_block('blockBODY', 'category', 'category_block');
		$this->t_body->set_block('blockBODY', 'file', 'file_block');
		
		$this->t_body->set_block('blockBODY', 'category_window', 'category_window_block');

		$this->t_body->set_var('category_block', '');
		$this->t_body->set_var('file_block', '');
		
		$this->outputCategories($parent, 'window');
		$this->outputFiles($parent);
		
		$this->t_body->pparse("finaloutput", "blockBODY");	
	}
}


$myPage = new OutPut((int)$_GET['category'], (int)$_GET['verbose']);


if (isset($_GET['catList'])) {
	
	$parent = (int)$_GET['catList'];

	$myPage->outputNav($parent);
	exit;
} else if (isset($_GET['fileList'])) {
	$parent = (int)$_GET['fileList'];

	$myPage->outputWindow($parent);
	exit;
}

$myPage->showPage();

?>