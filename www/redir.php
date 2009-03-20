<?php 
	require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/sitePage.php';
	$page = new SitePage;
	
	$url = '';
	
	if($_GET['url'])
		$url = $_GET['url'];		
	elseif (is_numeric($_GET['id'])) {
		// lookup link if id given
		require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/link.php';
		$linkHandler = new Link;
		$link = $linkHandler->getByID($_GET['id']);
		$url = $link['url'];
	}
	
	if (!empty($url)) {
		$url = urldecode($url);	
	
		if(!URLS_use_frame)
		{
			header('Location: '.$url);
			exit;
		}
	}
	else 
		error(2);
?>
<html>

	<head>
		<meta http-equiv="content-type" 
		content="text/html;charset=iso-8859-1">
		<title><?php echo HEADING; ?></title>
	</head>

	<frameset rows="*,26" border="0" framespacing="0" frameborder="no">
		<frame src="<?=$url?>" name="TopFrame" noresize>
		<frame src="banner.php" name="BottomFrame" noresize scrolling="no">
	</frameset>
	<noframes>

		<body bgcolor="#ffffff">
			<p></p>
		</body>

	</noframes>

</html>
