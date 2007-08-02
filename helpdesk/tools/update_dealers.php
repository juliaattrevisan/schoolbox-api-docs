<?php 

mysql_connect('localhost', 'helpdesk' , 'n00tbOOKS')
	or die( 'Couldn\'t connect to mysql.');
mysql_select_db( 'helpdesknew');

$query = mysql_query( "select serial from helpdesknew.tblEquipment");

while( $result = mysql_fetch_array( $query)) {
	$serial = $result[ 'serial'];

		# Get old dealer info
	$sql_old_dealer = "select dealer from helpdesk.MGS_Equipment where serialno='" . $serial . "'";
	$query2 = mysql_query( $sql_old_dealer);
	$row2 = mysql_fetch_array( $query2);
	$dealer_old = $row2[ 'dealer'];
	
	if( $dealer_old == '10000') {
		$sql_update = "update tblEquipment set dealerID='1' where serial='" . $serial  . "';";
		print $sql_update.'<br>';
		mysql_query( $sql_update);
	}
	else {
		print "do nothing for serial $serial<br>";
	}
}

mysql_close();

?>
