<?php 
mysql_connect('localhost', 'helpdesk' , 'n00tbOOKS')
	or die( 'Couldn\'t connect to mysql.');
mysql_select_db( 'helpdesknew');

$arrSpecID = array( 1, 2, 4, 6);

foreach( $arrSpecID as $specID) {
	print "<br><br>specID $specID :<br>";
	$sql = 'select distinct value from tblEquipmentSpec where specID='.$specID;
	$query = mysql_query( $sql);
	while( $row = mysql_fetch_array( $query)) {
		print $row[ 'value'] . '<br>';
	}
}

mysql_close();
?>
