<?php 
	require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/Bootstrap.php';
	$page = new SitePage;
	
	$url = '';
	
	if($_GET['url'])
		$url = base64_decode($_GET['url']);
	elseif (is_numeric($_GET['id'])) {
		// lookup link if id given
		$linkHandler = new Link;
		$link = $linkHandler->getByID($_GET['id']);
		$url = $link['url'];
        if($link) {
            $linkHandler->logAction($_GET['id'], 'access');
        }
	}
	
	if (!empty($url)) {
		header('Location: '.$url);
		exit;
	}
	else 
		error(2);
