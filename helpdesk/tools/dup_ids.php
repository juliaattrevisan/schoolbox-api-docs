<?php 

mysql_connect('localhost', 'helpdesk' , 'n00tbOOKS')
	or die( 'Couldn\'t connect to mysql.');
mysql_select_db( 'helpdesknew');

$sql = 'select e1.equipmentid
	from tblEquipment e1 inner join tblEquipment e2
	on e1.equipmentID = e2.equipmentID and
	(e1.serial != e2.serial or
	e1.modelID != e2.modelID)';

$query = mysql_query( $sql);

print '<table>';
print '<th>serial</th>';

while( $row = mysql_fetch_array( $query)) {
	$serial = $row[ 'serial'];
	print "<tr><td>$serial</td></tr>";
}

print '</table>';

print '<br><br>Done. ' . $mysql_num_rows( $query) . ' rows total.';

mysql_close();

?>
