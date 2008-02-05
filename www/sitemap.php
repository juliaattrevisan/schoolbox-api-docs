<?php

require_once('include/session.php');
require_once('include/include.php');
require_once('include/permission.php');

$db = new dbManager;
$t_body = new Template(TPL_directory);

if (isset($_GET['list'])) {
	
	$parent = (int)$_GET['list'];
	// list categories first
	$query = "SELECT 
				id, 
				name,
			  (SELECT count(*) FROM category WHERE parent = c.id) + (SELECT count(*) FROM file WHERE category = c.id) as children
			  FROM category c
			  WHERE parent = $parent ORDER BY name";	
	$catList = $db->dbGetAllRows($query);
	
	
	$t_body->set_file('tplBODY', 'sitemap_block.htm');
	$t_body->set_block('tplBODY', 'category', 'category_block');
	$t_body->set_block('tplBODY', 'file', 'file_block');
	
	$t_body->set_var('finaloutput', '');
	if (is_array($catList)) {
		foreach($catList as $category) {		
			$output = array ('CATEGORY_ID' 	 => $category['id'],
							 'CATEGORY_NAME' => $category['name'],
							 'IMAGE'		 => $category['children']>0?"<img src=\"/images/icons/sitemap-expand.gif\" width=\"12\" height=\"12\" alt=\"Expand\" />":""
							);
							
			$t_body->set_var($output);
			$t_body->parse('category_block', 'category', true);
	
		}
	} else
		$t_body->set_var('category_block', '');
	
	// list files
	$query = "SELECT id, description FROM file WHERE category = $parent ORDER BY description";
	$fileList = $db->dbGetAllRows($query);

	if (is_array($fileList)) {
		foreach ($fileList as $file) {
			$output = array ('FILE_ID' 	 => $file['id'],
							 'FILE_NAME' => $file['description'],
							);
							
			$t_body->set_var($output);
			$t_body->parse('file_block', 'file', true);

		}
	} else
		$t_body->set_var('file_block', '');

	
	$t_body->pparse("finaloutput", "tplBODY");
	exit;
}

$t_body->set_file('tplBODY', 'sitemap.htm');




$t_body->set_var('category_block', '');
$t_body->set_var('file_block', '');

$t_body->pparse("finaloutput", "tplBODY");
?>