<?php

/************************************************************
*                                                           *
*         Copyright (c) 2002-2003 Lektec Consulting         *
*                                                           *
************************************************************/

/* Allow users to customise their ePortfolios
 */
require_once $_SERVER['DOCUMENT_ROOT'] .'/../library/eportfolio.php';

if (is_numeric($this->arg[0])) {
	$uid = $this->arg[0];

	if($_SESSION['id'] != $uid) {
		error(19);
    }

	$eportfolioHandler = new ePortfolio($uid);

	$this->setFile(TEMPLATE_content, 'eportfolio/customise.html');
	$this->setTitle('ePortfolio Customisation');
	$this->setTab('resources');

	/* Template control
     */
	$template = $eportfolioHandler->getTemplate();

	for( $x = 1; $x <= 4; $x++) {
        $this->setVar(array(
            "CURRENT_$x" => $template == $x ? 'current' : '',
        ));
	}

	$this->setVar(array(
        'UID'		=> $uid,
        'TEMPLATE'	=> $template,
    ));

	/* Components
     */
	$this->setBlock(TEMPLATE_content, 'componentRow', 'componentRowBlock');
	$this->setBlock('componentRow', 'componentCol', 'componentColBlock');

	$listComp = $eportfolioHandler->getComponentList();

	foreach($listComp as $key => $component){
		$this->setVar('componentID', $component['id']);
		$this->setVar('componentName', $component['name']);
		$this->setVar('componentDescription', $component['description']);

		if (!empty($component['component'])) {
			$this->setVar('componentChecked', 'checked="checked"');
        }
		else {
			$this->setVar('componentChecked', '');
        }

		$this->parse('componentColBlock', 'componentCol', true);

        if ((++$key % 3) == 0 or $component == end($listComp)) {
			$this->parse('componentRowBlock', 'componentRow', true);
			$this->setVar('componentColBlock','');
		}
	}

	$this->setBlock('custom_head', 'colour_list', 'colour_list_block');

	/* Colors
     */
	$listColor = $eportfolioHandler->getColorList();

	$this->setVar('COLOUR_ARRAY', 'pickers');
	$this->setVar($listColor);
	$this->parse('colour_list_block', 'colour_list', true);

	/* Defaults
     */
	$defaultColour = unserialize(HOMEPAGE_colors);

	$this->setVar('COLOUR_ARRAY', 'defaults');
	$this->setVar($defaultColour);
	$this->parse('colour_list_block', 'colour_list', true);
}
else {
	error(18);
}
?>
