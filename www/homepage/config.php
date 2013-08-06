<?php

/************************************************************
*                                                           *
*         Copyright (c) 2002-2003 Lektec Consulting         *
*                                                           *
************************************************************/

//allow users to customise their homepages

$groupHandler = new Group(HOMEPAGE_SERVICE);

if(is_numeric($this->arg[0])) {
	
	$cid = $this->arg[0];

	if($groupHandler->getPermission($cid) < WRITE_ACCESS) 
		error(19);
	
	$homepage = new Homepage($cid);
	
	$this->setFile(TEMPLATE_content, 'homepage/customise.html');
	$this->setTitle('Homepage Customisation');
	$this->setTab('resources');
	
	//Template control
	$template = $homepage->getTemplate();
	for($x = 1; $x <= 4; $x++) {
		$output = array("CURRENT_$x"	=> $template == $x ? 'current' : '',
						);
		$this->setVar($output);
	}

	$output = array('CID'		=> $cid,
					'TEMPLATE'	=> $template,
					);
	$this->setVar($output);
	
	// Components
	$this->setBlock(TEMPLATE_content, 'componentRow', 'componentRowBlock');
	$this->setBlock('componentRow', 'componentCol', 'componentColBlock');
	
	$listComp = $homepage->getComponentList();
	foreach($listComp as $key => $component){
		$this->setVar('componentID', $component['id']);
		$this->setVar('componentName', $component['name']);
		$this->setVar('componentDescription', $component['description']);
		if(!empty($component['component']))
			$this->setVar('componentChecked', 'checked="checked"');
		else
			$this->setVar('componentChecked', '');

		
		$this->parse('componentColBlock', 'componentCol', true);
		
		if((++$key % 3) == 0 or $component == end($listComp)) {
			$this->parse('componentRowBlock', 'componentRow', true);
			$this->setVar('componentColBlock','');
		}
	}
			
	
	$this->setBlock('custom_head', 'colour_list', 'colour_list_block');
	// Colors
	$listColor = $homepage->getColorList();
	$this->setVar('COLOUR_ARRAY', 'pickers');
	$this->setVar($listColor);
	$this->parse('colour_list_block', 'colour_list', true);
	
	// defaults
	$defaultColour = unserialize(HOMEPAGE_colors);
	$this->setVar('COLOUR_ARRAY', 'defaults');
	$this->setVar($defaultColour);
	$this->parse('colour_list_block', 'colour_list', true);
}
else
	error(18);

?>
