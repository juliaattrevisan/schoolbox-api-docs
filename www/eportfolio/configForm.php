<?php

/************************************************************
*                                                           *
*         Copyright (c) 2002-2003 Lektec Consulting         *
*                                                           *
************************************************************/

//allow users to customise their homepages

require_once $_SERVER['DOCUMENT_ROOT'] .'/../library/eportfolio.php';
require_once $_SERVER['DOCUMENT_ROOT'] .'/../include/sitePage.php';

$page = new SitePage;

if (is_numeric($_REQUEST['uid']) ) {

    /* Check that you own the current ePortfolio being viewed
     */
	if ($_SESSION['id'] != $_REQUEST['uid']) {
		error(19);
    }
	
	$eportfolioHandler = new ePortfolio($_REQUEST['uid']);
	
	/* AJAX request for drag and drop of components
     * This is found in jquery.eportfoliocomponent.js
     */
	if ($_POST['uid'] && $_POST['type'] == 'components') {
		$columns = array($_POST['column1'], $_POST['column2']);

		foreach($columns as $key => $column) {
			/* Explode the columns
             */
			$key++;
			$components[$key] = explode(',', $column);
	
			/* Update the db
             */
			foreach($components[$key] as $ckey => $component) {
				$ckey++;
				$eportfolioHandler->updateComponentPosition($component, $key, $ckey);
			}
		}
	}
	else if ($_GET['uid'] && $_GET['type'] == 'config' && $_GET['component']) {
        
		/* Post config options from homepage
         */
		$config = $_GET;
		unset($config['uid'], $config['type'], $config['component']);

		/* All remaining options and serialised and sent to db
         */
		$config = serialize($config);
		$eportfolioHandler->updateComponentConfig($_GET['component'], $config);
        
		localRedir('/eportfolio/' . $_GET['uid']);
	}
	else if (is_numeric($_POST['uid'])) {

		/* Submission of customise page
         */
		$eportfolioHandler->savePageTemplate($_POST['template']);
		$eportfolioHandler->updateComponents($_POST['components']);
		
		/* Strip hash
         */
		if (is_array($_POST['colors'])) {
			foreach($_POST['colors'] as $key => $value) {
				$color[$key] = substr($value,1);
			}
			
			$eportfolioHandler->saveDisplayProperties($color);
		}

		localRedir('/eportfolio/' . $_POST['uid']);
	}
}
else {
	error(2);
}
?>
