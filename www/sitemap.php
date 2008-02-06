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
		
		//category output
		//$this->t_body->set_block("tplBODY", "category_listing", "cats_block");
		//$this->t_body->set_block("category_listing", "mod_cat", "mod_cat_section");		
	
		//file output
		//$this->t_body->set_block("tplBODY", "file_listing", "files_block");
		//$this->t_body->set_block("file_listing", "file_desc", "file_block");
		//$this->t_body->set_block("file_listing", "mod_file", "mod_file_section");		

		//link output
		//$this->t_body->set_block("tplBODY", "link_listing", "links_block");
		//$this->t_body->set_block("link_listing", "link_desc", "link_block");
		//$this->t_body->set_block("link_listing", "mod_link", "mod_link_section");		

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
				  (SELECT count(*) FROM category WHERE parent = c.id) + (SELECT count(*) FROM file WHERE category = c.id) as children
				  FROM category c
				  WHERE parent = $parent ORDER BY name";	
		$catList = $this->db->dbGetAllRows($query);

		

		
	//	$this->t_body->set_var('finaloutput', '');
		if (is_array($catList)) {
			foreach($catList as $category) {		
				$output = array ('CAT_ID' 		 => $category['id'],
								 'CAT_NAME'      => $category['name'],
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
		
		$query = "SELECT file.id, file.description, iconFile, allowedFileTypes.description as mime_description
				  FROM file 
				  LEFT JOIN allowedFileTypes ON file.mime_type = allowedFileTypes.mime
				  WHERE category = $parent ORDER BY description";
		$fileList = $this->db->dbGetAllRows($query);
		
		if (is_array($fileList)) {
			foreach ($fileList as $file) {
				$output = array ('FILE_ID' 	 => $file['id'],
								 'FILE_NAME' => $file['description'],
								 'FILE_ICON' => !is_null($file['iconFile']) ? "/images/mime/".$file['iconFile'] : "/images/mime/application-x-none.png",
								 'FILE_ALT'  => !is_null($file['mime_description']) ? $file['mime_description'] : "File",
	
								);
								
				$this->t_body->set_var($output);
				$this->t_body->parse('file_window_block', 'file', true);
	
			}
		} else
			$this->t_body->set_var('file_window_block', '');	
	
	}

	function outputNav($parent) {

		$this->t_body->set_file('blockBODY', 'sitemap_block.htm');
		$this->t_body->set_block('blockBODY', 'category', 'category_block');
		$this->t_body->set_block('blockBODY', 'file', 'file_block');
		
		$this->t_body->set_block('blockBODY', 'category_window', 'category_window_block');
		$this->t_body->set_block('blockBODY', 'file_window', 'file_window_block');
		
		$this->t_body->set_var('category_window_block', '');
		$this->t_body->set_var('file_window_block', '');
		$this->t_body->set_var('file_block', '');
		
		$this->outputCategories($parent, 'nav');
		
		$this->t_body->pparse("finaloutput", "blockBODY");
		
	}
	
	function outputWindow($parent) {
	
		$this->t_body->set_file('blockBODY', 'sitemap_block.htm');
		$this->t_body->set_block('blockBODY', 'category', 'category_block');
		$this->t_body->set_block('blockBODY', 'file', 'file_block');
		
		$this->t_body->set_block('blockBODY', 'category_window', 'category_window_block');
		$this->t_body->set_block('blockBODY', 'file_window', 'file_window_block');

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