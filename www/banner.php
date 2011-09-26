<?php
	include('conf/conf.php');
?>
<html>
	<head>
		<meta http-equiv="content-type" content="text/html;charset=iso-8859-1">
		<title><? include("conf/conf.php"); echo HEADING;?></title>

		<script type = "text/javascript">

	   function closeWindow(){
			window.open('','_self','');
		   window.close();
	   }

		</script>
	</head>
	<body bgcolor="black" topmargin="1" marginheight="1" leftmargin="5" marginwidth="5">
	<div align="left">
		<table border="0" cellpadding="0" cellspacing="0" width="100%">
			<tr>
				<td align="left">
					<span style="color:#fff; font-size:12px;font-family:Arial, Helvetica, sans-serif; ">Finished? Click the delete icon to close this window and return to the <? echo HEADING;?>.</span>

					</td>
				<td align="right" valign="middle">
					<a href="#"  onclick="closeWindow()" style="padding:4px; display:block;"><img src="/images/famicons/delete.png"  border="0" /></a>
				</td>
			</tr>
		</table>
	</div>
	</body>
</html>
