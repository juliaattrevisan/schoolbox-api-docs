<?php 
	include_once("include/include.php");
	require_once("include/session.php");
	
	if(SITE_location == 'wenona' && stristr($_GET['url'], INTRANET_site))
	{
		$url = $_GET['url'].'?strEncrypted='.base64_encode(xorEncrypt(INTRANET_encryption_key, $_SESSION['username'].' '.$_SESSION['password']));
		//print $url;
	}
	else
		$url = $_GET['url'];	

	if(!URLS_use_frame)
	{
		header('Location: '.$url);
		exit;
	}
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
