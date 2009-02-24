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
	$this->setBlock(TEMPLATE_content, 'componentRow', 'componentRowBlock');
	$this->setBlock('componentRow', 'componentCol', 'componentColBlock');
	
	$listComp = $homepage->getComponentList(0);
	foreach($listComp as $key => $component){
		$this->setVar('componentID', $component['id']);
		$this->setVar('componentName', $component['name']);
		$this->setVar('componentDescription', $component['description']);
		if(!empty($component['component']))
			$this->setVar('componentChecked', 'checked');
		else
			$this->setVar('componentChecked', '');

		
		$this->parse('componentColBlock', 'componentCol', true);
		
		if((++$key % 3) == 0 or $component == end($listComp)) {
			$this->parse('componentRowBlock', 'componentRow', true);
			$this->setVar('componentColBlock','');
		}
	}
			
	// Colors
	$listColor = $homepage->getColorList();
	$this->setVar($listColor);
}
else
	error(18);

?>
