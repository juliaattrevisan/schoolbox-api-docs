<html>

	<head>
		<meta http-equiv="content-type" content="text/html;charset=iso-8859-1">
		<title><? include("../conf/conf.php"); echo HEADING; ?></title>
	</head>

	<frameset rows="*,26" border="0" framespacing="0" frameborder="no">
		<frame src="/mail/download.php?msgid=<?=$_GET['msgid']?>&pid=<?=$_GET['pid']?>" name="TopFrame" noresize>
		<frame src="sendbanner.php?msgid=<?=$_GET['msgid']?>&pid=<?=$_GET['pid']?>" name="BottomFrame" noresize scrolling="no">
	</frameset>
	<noframes>

		<body bgcolor="#ffffff">
			<p></p>
		</body>

	</noframes>

</html>
