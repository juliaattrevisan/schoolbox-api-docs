<?php
	include('conf/conf.php');
?>
<html>
	<head>
		<meta http-equiv="content-type" content="text/html;charset=iso-8859-1">
		<title><? include("conf/conf.php"); echo HEADING;?></title>
	</head>
	<body bgcolor="black" topmargin="1" marginheight="1" leftmargin="5" marginwidth="5">
	<div align="left">
		<table border="0" cellpadding="0" cellspacing="0" width="100%">
			<tr>
				<td align="left">
					<font color="white" size="2">
			Finished? Click the cross to close this window and return to the <? echo HEADING;?>.
					</font>
					</td>
				<td align="right">
			<a href='javascript:top.close()'><img src="/images/cross.gif" width="25" height="25" border="0" align="absmiddle"></a>
				</td>
			</tr>
		</table>
	</div>
	</body>
</html>
