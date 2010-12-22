<?php

/************************************************************
*                                                           *
*         Copyright (c) 2002-2003 Lektec Consulting         *
*                                                           *
************************************************************/

//allow users to customise their homepages

require_once $_SERVER['DOCUMENT_ROOT'] .'/../library/homepage.php';
require_once $_SERVER['DOCUMENT_ROOT'] .'/../library/group.php';
require_once $_SERVER['DOCUMENT_ROOT'] .'/../include/sitePage.php';
$page = new SitePage;

$groupHandler = new Group(HOMEPAGE_SERVICE);

if(is_numeric($_REQUEST['cid']) ) {
	

	if($groupHandler->getPermission($_REQUEST['cid']) < WRITE_ACCESS) 
		error(19);
	
	$homepage = new Homepage($_REQUEST['cid']);
	
	//AJAX request for drag and drop of components
	if($_POST['cid'] && $_POST['type'] == 'components')
	{	
		$columns = array($_POST['column1'], $_POST['column2']);
		foreach($columns as $key => $column)
		{
			// Explode the columns
			$key++;
			$components[$key] = explode(',', $column);
	
			// Update the db
			foreach($components[$key] as $ckey => $component)
			{
				$ckey++;
				$homepage->updateComponentPosition($component, $key, $ckey);
			}
		}
	}
    elseif($_POST['cid'] && is_numeric($_POST['delete'])) {
        //AJAX request to delete component from homepage
        $homepage->deleteComponent($_POST['delete']);
    }
    else if($_POST['cid'] && is_numeric($_POST['add'])) {
        //Add component to page and output render of component
        $homepage->addComponent($_POST['add']);

        //Output component HTML

    }
	elseif($_GET['cid'] && $_GET['type'] == 'config' && $_GET['component'])
	{	
		//Post config options from homepage
		$config = $_GET;
		unset($config['cid'], $config['type'], $config['component']);
		//all remaining options and serialised and sent to db
		$config = serialize($config);
		$homepage->updateComponentConfig($_GET['component'], $config);
		localRedir("/homepage/".$_GET['cid']);
	}
    /*
	elseif(is_numeric($_POST['cid']))
	{
		//Submission of customise page
		$homepage->savePageTemplate($_POST['template']);
		$homepage->updateComponents($_POST['components']);
		
		//strip hash
		if (is_array($_POST['colors'])) {
			foreach($_POST['colors'] as $key => $value) {
				$color[$key] = substr($value,1);
			}
			
			$homepage->saveDisplayProperties($color);
		}

		localRedir("/homepage/".$_POST['cid']);
	}
     * 
     */
}
else
	error(2);

?>
