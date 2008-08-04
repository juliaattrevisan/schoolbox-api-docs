<?php 
	include_once("include/include.php");
	require_once("include/session.php");
	
	if($_GET['url']) {
		$url = urldecode($_GET['url']);	
	
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
