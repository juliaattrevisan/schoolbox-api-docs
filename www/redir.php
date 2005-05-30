<?php 
	include("conf/conf.php");
	
	if(!URLS_use_frame)
	{
		header("Location: ".$_GET['url']);
		exit;
	}
?>
<html>

	<head>
		<meta http-equiv="content-type" 
		content="text/html;charset=iso-8859-1">
		<title><?php include("conf/conf.php"); echo HEADING; ?></title>
	</head>

	<frameset rows="*,26" border="0" framespacing="0" frameborder="no">
		<frame src="<?=$_GET['url']?>" name="TopFrame" noresize>
		<frame src="banner.php" name="BottomFrame" noresize scrolling="no">
	</frameset>
	<noframes>

		<body bgcolor="#ffffff">
			<p></p>
		</body>

	</noframes>

</html>
