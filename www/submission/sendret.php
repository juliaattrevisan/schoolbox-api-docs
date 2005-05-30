<html>

	<head>
		<meta http-equiv="content-type" content="text/html;charset=iso-8859-1">
		<title><? include("../conf/conf.php"); echo HEADING; ?></title>
	</head>

	<frameset rows="*,26" border="0" framespacing="0" frameborder="no">
		<frame src="returnedWork.php?view=<?=$_GET['view']?>" name="TopFrame" noresize>
		<frame src="retbanner.php?view=<?=$_GET['view']?>" name="BottomFrame" noresize scrolling="no">
	</frameset>
	<noframes>

		<body bgcolor="#ffffff">
			<p></p>
		</body>

	</noframes>

</html>
