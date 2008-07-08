<?php
/************************************************************
*                                                           *
*         Copyright (c) 2002-2003 Lektec Consulting         *
*                                                           *
************************************************************/

// fix url
require_once 'include/stdfuncs.php';
if (strpos($_SERVER['REQUEST_URI'], ".php?category=") ||
	strpos($_SERVER['REQUEST_URI'], ".php?code=")) {
	$category = isset($_GET['category']) ? $_GET['category'] : $_GET['code'];
	localRedir("/homepage/" . $category);
}

require_once 'include/permission.php';
require_once 'include/involved.php';
require_once '../library/category.php';

$categoryHandler = new Category; 
if(is_numeric($this->arg[0]))
	$category = $categoryHandler->getByID($this->arg[0]);
if(!is_array($category)) {
	$category = $categoryHandler->getByField("code", urldecode($this->arg[0]));
	$category = $categoryHandler->getByID($category['id']);
}


$categoryID =& $category['id'];
if (!$categoryID)
	error(18);


$categoryHandler->logPageView($categoryID);

define('MAX_COLUMN_COUNT',2);
define("CATEGORY_perms", perm($categoryID, HOMEPAGESERVICE));

// check perms
if (CATEGORY_perms < 1)
	error(14);



if($category['code'])
	$enroled = involvedInSubject($category['code']);
else
	$enroled = involvedInGroup($categoryID);


$this->setFile(TEMPLATE_content, 'homepage/template' . $category['template'] . '.html');
$this->setTitle($category['name']);

$this->setBlock('custom_head', 'modify_layout', 'modify_layout_block');
$this->setBlock(TEMPLATE_content, "edit_page", "edit_page_block");

$output = array('LARGE_HEAD_BGCOLOUR'	=> $category['large_head_bgcolour'],
				'LARGE_HEAD_FONTCOLOUR'	=> $category['large_head_fontcolour'],
				'LARGE_TEXT_BGCOLOUR'	=> $category['large_text_bgcolour'],
				'LARGE_TEXT_FONTCOLOUR'	=> $category['large_text_fontcolour'],
				'SMALL_HEAD_BGCOLOUR'	=> $category['small_head_bgcolour'],
				'SMALL_HEAD_FONTCOLOUR'	=> $category['small_head_fontcolour'],
				'SMALL_TEXT_BGCOLOUR'	=> $category['small_text_bgcolour'],
				'SMALL_TEXT_FONTCOLOUR'	=> $category['small_text_fontcolour'],
				'HEADING'				=> scrubTEXT($category['name']) . (isset($code) ? '('.urlencode($category['code']).')' : '' ),
				'CODE'					=> urlencode($category['code']),
				'ID'					=> $categoryID,
				);

$this->setVar($output);

if(CATEGORY_perms >= 2) {//has elevated priviliges, can write to page
	$this->parse("edit_page_block", "edit_page");
	$this->parse('modify_layout_block', 'modify_layout');
}	
else {
	$this->setVar("edit_page_block", "");
	$this->setVar('modify_layout_block', '');
}

$temp = unserialize('a:3:{s:4:"sort";s:4:"Date";s:7:"sortdir";s:4:"Desc";s:4:"size";s:1:"0";}');

$temp['filter'] = array('',
						array('All'	=> '',
							  'Documents'	=> 'document',						 
							 ),
						);
//print serialize($temp);

$componentList = $categoryHandler->getComponents($categoryID);
if (is_array($componentList)) {

	$componentCount = 0;
	foreach ($componentList as $component) {
		
		$componentCount++;
		$componentHandle = $component['name'] . $componentCount;

		$this->setFile($componentHandle, 'homepage/components/'.$component['filename']);
		$this->setBlock($componentHandle, "config", "config_block");
		
		$this->setVar('COMPONENT_ID', $component['component']);
		
		$default = unserialize($component['dfconfig']);
		$config = unserialize($component['config']);

		if(is_array($default) && CATEGORY_perms >= 2) {	

			foreach($default as $name => $value) {
			
				switch ($name) {

					case "size":
						$this->setBlock("config", "size", "size_block".$componentCount);
						for($i=0; $i<=10; $i++) {
							$this->setVar('COMPONENT_CONFIG_SIZE_NAME', $i == 0 ? 'All' : $i);
							$this->setVar('COMPONENT_CONFIG_SIZE_VALUE', $i);
							$this->setVar('COMPONENT_CONFIG_SIZE_SELECTED', $i == $config[$name] ? ' selected="selected"' : '');
							$this->parse("size_block".$componentCount, "size", true);
						}
						break;
						
					case "image":
						$this->setBlock("config", "image", "image_block".$componentCount);
						
						$imageList = $categoryHandler->getImages($categoryID);
						if(is_array($imageList)){
							foreach ($imageList as $image) {
							
								$this->setVar('COMPONENT_CONFIG_IMAGE', $image['description']);
								$this->setVar('COMPONENT_CONFIG_IMAGE_SELECTED', $image['description'] == $config[$name] ? ' selected="selected"' : '');
								$this->parse("image_block".$componentCount, "image", true);
							}
						}
						break;
						
					case "sort":		
						$this->setBlock("config", "sort", "sort_block".$componentCount);
						$sortOptions = array('Name', 'Date');
						foreach ($sortOptions as $sort)
						{
							$this->setVar('COMPONENT_CONFIG_SORT_NAME', $sort);
							$this->setVar('COMPONENT_CONFIG_SORT_VALUE', $sort);
							$this->setVar('COMPONENT_CONFIG_SORT_SELECTED', $sort == $config[$name] ? ' selected="selected"' : '');
							$this->parse("sort_block".$componentCount, "sort", true);
						}
						break;
						
					case "sortdir":
						$this->setBlock("config", "sortdir", "sortdir_block".$componentCount);
						$sortOptions = array('Asc', 'Desc');
						foreach ($sortOptions as $sort)
						{
							$this->setVar('COMPONENT_CONFIG_SORTDIR_NAME', $sort);
							$this->setVar('COMPONENT_CONFIG_SORTDIR_VALUE', $sort);
							$this->setVar('COMPONENT_CONFIG_SORTDIR_SELECTED', $sort == $config[$name] ? ' selected="selected"' : '');
							$this->parse("sortdir_block".$componentCount, "sortdir", true);
						}
						break;
					case "filter":
						$this->setBlock("config", "filter", "filter_block".$componentCount);
						list($value, $filterOptions) = $value;
						foreach ($filterOptions as $fname => $fvalue) 
						{
							$this->setVar('COMPONENT_CONFIG_FILTER_NAME', $fname);
							$this->setVar('COMPONENT_CONFIG_FILTER_VALUE', $fvalue);
							$this->setVar('COMPONENT_CONFIG_FILTER_SELECTED', $fvalue == $config[$name] ? ' selected="selected"' : '');
							$this->parse("filter_block".$componentCount, "filter", true);							
						}
						break;
					default:
						$this->setVar('COMPONENT_CONFIG_' . strtoupper($name), $config[$name]);
				}
			}
			$this->parse("config_block", "config");	
		
		} else
			$this->setVar("config_block", "");	
		
		
		if(is_file($_SERVER['DOCUMENT_ROOT'] . '/homepage/' . $component['method'] . '.php')) {
			include($_SERVER['DOCUMENT_ROOT'] . '/homepage/' . $component['method'] . '.php');
			$this->parse('COLUMN'.$component['column'], $componentHandle, true);
			
			$columnFlag[$component['column']] = true;
		}
	}
}

//set any columns with nothing in them to null
for($i=1; $i <= MAX_COLUMN_COUNT; $i++){
	if(!$columnFlag[$i])
		$this->setVar('COLUMN'.$i,'');
}


?>
