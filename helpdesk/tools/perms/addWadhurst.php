<?php

# Add read perms for wadhurst students to all categories

$s = 'select distinct cid from perms where gid != 4';
mysql_connect( 'localhost', 'intranet', 'Qu4LitY');
mysql_select_db( 'intranet');
$q = mysql_query( $s);
while( $r = mysql_fetch_array( $q)) {
	$cid = $r[ 'cid'];
	$s = "insert into perms (cid, gid, type) values ($cid, 4, 1)";
	print $s.'<br>';
	#mysql_query( $s);
}
mysql_close();
?>
