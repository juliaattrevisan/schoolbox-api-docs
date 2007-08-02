<?php 

mysql_connect('localhost', 'helpdesk' , 'n00tbOOKS')
	or die( 'Couldn\'t connect to mysql.');
mysql_select_db( 'helpdesknew');

$query = mysql_query( "select * from helpdesknew.tblEquipment");

$i=0;

while( $result = mysql_fetch_array( $query)) {
	$serial = $result[ 'serial'];

	$sql_id = "select id from helpdesk.MGS_Equipment where serialno='" . $serial . "'";
	$query2 = mysql_query( $sql_id);
	$row2 = mysql_fetch_array( $query2);
	$id = $row2[ 'id'];
	
	if( ($id <> '') and ($serial <> '') and !preg_match( '/\?/', $serial) and isset( $id)) {
		$sql_update = "update helpdesknew.tblEquipment set equipmentID=$id where serial='" . $serial  . "'";
		print $sql_update.'<br>';
		mysql_query( $sql_update);
	}
	else {
		$i++;
		#foreach( array_keys( $result) as $key) {
			#$exceptions .= $key . ': ' . $result[ $key] . ', ';
		#}
		#print_r( $result);
		#print "<br><br>";
		$exceptions .= "id: $id , serial $serial" . '<br>';
	}
}

mysql_close();

print '<h2>Exception serials (' . $i . ' in total):</h2>' . $exceptions;

?>
