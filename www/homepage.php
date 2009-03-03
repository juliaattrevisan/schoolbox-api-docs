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

require_once $_SERVER['DOCUMENT_ROOT'] . '/include/involved.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/category.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/homepage.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/group.php';

$categoryHandler = new Category; 
$groupHandler = new Group(HOMEPAGE_SERVICE);

if(is_numeric($this->arg[0]))
	$category = $categoryHandler->getByID($this->arg[0]);
if(!is_array($category)) {
	$code = implode('/', $this->arg);
	$category = $categoryHandler->getByCode(urldecode(urldecode($code)));
	$category = $categoryHandler->getByID($category['id']);
}


$categoryID =& $category['id'];
if (!$categoryID) {
	error(18);
}

$homepageHandler = new Homepage($category['id']);

$categoryHandler->logPageView($categoryID);

define('MAX_COLUMN_COUNT',2);

// check perms
if (($permission = $groupHandler->getPermission($categoryID)) < READ_ACCESS)
	error(14);


if(!isset($category['template']))
	localRedir('/resources/'.$categoryID);

if($category['code']) {
	$enroled = $groupHandler->involvedInSubject($category['code']);
	$code = $category['code'];
}
else
	$enroled = $groupHandler->involvedInGroup($_SESSION['id'], $categoryID);


$this->setFile(TEMPLATE_content, 'homepage/template.html');
$this->setTitle($category['name']);
$this->setTab('resources');

$this->setBlock('custom_head', 'modify_layout', 'modify_layout_block');
$this->setBlock(TEMPLATE_content, "edit_page", "edit_page_block");

$colours = $homepageHandler->getColorList();

$output = array('LARGE_HEAD_BGCOLOUR'	=> $colours['large_head_bgcolour'],
				'LARGE_HEAD_FONTCOLOUR'	=> $colours['large_head_fontcolour'],
				'LARGE_TEXT_BGCOLOUR'	=> $colours['large_text_bgcolour'],
				'LARGE_TEXT_FONTCOLOUR'	=> $colours['large_text_fontcolour'],
				'SMALL_HEAD_BGCOLOUR'	=> $colours['small_head_bgcolour'],
				'SMALL_HEAD_FONTCOLOUR'	=> $colours['small_head_fontcolour'],
				'SMALL_TEXT_BGCOLOUR'	=> $colours['small_text_bgcolour'],
				'SMALL_TEXT_FONTCOLOUR'	=> $colours['small_text_fontcolour'],
				'HEADING'				=> scrubTEXT($category['name']) . (isset($code) ? ' ('.$category['code'].')' : '' ),
				'CODE'					=> urlencode(urlencode($category['code'])),
				'ID'					=> $categoryID,
				'TEMPLATE'				=> 'template'.$category['template'],
				);

$this->setVar($output);

if($permission >= WRITE_ACCESS) { //has elevated priviliges, can write to page
	$this->parse("edit_page_block", "edit_page");
	$this->parse('modify_layout_block', 'modify_layout');
}	
else {
	$this->setVar("edit_page_block", "");
	$this->setVar('modify_layout_block', '');
}

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

		if(is_array($default) && $permission >= WRITE_ACCESS) {	

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
