<?php
# File		: reportCourier.php
# Written	: Nick Doyle, 19/11/2002
# Description	: report on notebooks awaiting courier pickup
#		  Currently gets all jobs ready for pickup (notebook or otherwise)

require_once( 'include/helpDeskPage.php');

	$tpl = new Module(TPL_DIR);
	$tpl->set_file('tplContent', 'reportIndex.html');

	$page = new HelpDeskPage;
	$page->authCheck(1);
	
	//
	
		$tpl->set_block('tplContent', 'level1Block', 'block1');
		$tpl->set_block('tplContent', 'level2Block', 'block2');
		$tpl->set_block('tplContent', 'level3Block', 'block3');
	//
	switch( $_SESSION[ 'authlevel'] )
	{
		case 1:		$tpl->set_var('block2');
					$tpl->set_var('block3');
					$tpl->parse('block1', 'level1Block');
		break;
		case 2: 	$tpl->set_var('block3');
					$tpl->parse('block1', 'level1Block');
					$tpl->parse('block2', 'level2Block');
		break;
		case 3:
					$tpl->parse('block1', 'level1Block');
					$tpl->parse('block2', 'level2Block');
					$tpl->parse('block3', 'level3Block');
		break;
		default: print( "Unkonwn");
	}

$content = $tpl->parse('Output', 'tplContent');
$page->setContent($content);
$page->setTitle("Report Index");
$page->printPage();
		

?>
