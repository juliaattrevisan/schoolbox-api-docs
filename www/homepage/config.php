<?php

/************************************************************
*                                                           *
*         Copyright (c) 2002-2003 Lektec Consulting         *
*                                                           *
************************************************************/

//allow users to customise their homepages
require_once $_SERVER['DOCUMENT_ROOT'] .'/../library/homepage.php';
require_once $_SERVER['DOCUMENT_ROOT'] .'/../library/group.php';

$groupHandler = new Group(HOMEPAGE_SERVICE);

if(is_numeric($this->arg[0])) {
	
	$cid = $this->arg[0];

	if($groupHandler->getPermission($cid) < WRITE_ACCESS) 
		error(19);
	
	$homepage = new Homepage($cid);
	
	$this->setFile(TEMPLATE_content, 'homepage/customise.html');
	$this->setTitle('Homepage Customisation');
	$this->setVar('CID', $cid);
	
	//Template control
	$template = $homepage->getTemplate();
	for($x = 1; $x <= 3; $x++)
	{
		if($template == $x)
			$this->setVar("templateChecked$x", "checked");
		else
			$this->setVar("templateChecked$x", "");
	}
	
	// Components
	$this->setBlock(TEMPLATE_content, 'componentGroup', 'componentGroupBlock');
	$this->setBlock('componentGroup', 'componentRow', 'componentRowBlock');
	$this->setBlock('componentRow', 'componentCol', 'componentColBlock');
	$this->setVar('componentGroupName', 'Large Components');
	
	$listLargeComp = $homepage->getComponentList(1);
	foreach($listLargeComp as $key => $largeComp){
		$this->setVar('componentID', $largeComp['id']);
		$this->setVar('componentName', $largeComp['name']);
		$this->setVar('componentDescription', $largeComp['description']);
		if(!empty($largeComp['component']))
			$this->setVar('componentChecked', 'checked');
		else
			$this->setVar('componentChecked', '');

		
		$this->parse('componentColBlock', 'componentCol', true);
		
		if((++$key % 2) == 0 or $largeComp == end($listLargeComp)) {
			$this->parse('componentRowBlock', 'componentRow', true);
			$this->setVar('componentColBlock','');
		}
	}
	
	$this->parse('componentGroupBlock', 'componentGroup',true);
	
	$this->setVar('componentColBlock','');
	$this->setVar('componentRowBlock','');
	$this->setVar('componentGroupName', 'Small Components');
	
	$listSmallComp = $homepage->getComponentList(2);
	foreach($listSmallComp as $key => $smallComp){
		$this->setVar('componentID', $smallComp['id']);
		$this->setVar('componentName', $smallComp['name']);
		$this->setVar('componentDescription', $smallComp['description']);
		if(!empty($smallComp['component']))
			$this->setVar('componentChecked', 'checked');
		else
			$this->setVar('componentChecked', '');
		
		$this->parse('componentColBlock', 'componentCol', true);
		
		if($key % 2 or $smallComp == end($listSmallComp)) {
			$this->parse('componentRowBlock', 'componentRow', true);
			$this->setVar('componentColBlock','');
		}
	}
	
	$this->parse('componentGroupBlock', 'componentGroup',true);
			
	// Colors
	$listColor = $homepage->getColorList();
	$this->setVar($listColor);
}
else
	error(18);

?>
