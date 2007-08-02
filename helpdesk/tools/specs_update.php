<?php 

# Sample line in column TEMP_SPECS :
#
# 1~333mhz&2~64&3~4G&4~40x&5~No&6~Yes&7~Yes&8~No&9
#
# Translates to stat1=333mhz, stat2=64 etc ...
# If no value specified, ignore

mysql_connect('localhost', 'helpdesk' , 'n00tbOOKS')
	or die( 'Couldn\'t connect to mysql.');
mysql_select_db( 'helpdesknew');

$query = mysql_query( "select equipmentID, TEMP_SPECS, equipmentTypeID from helpdesknew.tblEquipment where TEMP_SPECS is not null and equipmentTypeID=2 order by equipmentTypeID");

if( mysql_error() <> '') {
	die( mysql_error());
}

#print '<table border=1>
#	<tr><th>id</th><th>type</th><th>specs</th><th>proc</th><th>memory</th><th>hdd</th><th>cd</th><th>dvd</th><th>soundcard</th><th>network</th><th>modem</th><th>os</th></tr>';

while( $result = mysql_fetch_array( $query)) {
	$type = $result[ 'equipmentTypeID'];
	$equipmentID = $result[ 'equipmentID'];
	$specs = $result[ 'TEMP_SPECS'];

	#$specNames = array(	1=>'processor', 
				#2=>'memory',
				#3=>'HDD',
				#4=>'CD',
				#5=>'DVD',
				#6=>'Sound card',
				#7=>'Network card',
				#8=>'Modem',
				#9=>'OS',
				#10=>'Other',

	$arrSpecs = array();

	preg_match( '/(1~([\w\d\s\.]+))/', $specs, $matches);
	$processor = $matches[2];
	$spec = array(	'id'	=>	1,
			'value' =>	$matches[2]);
	array_push( $arrSpecs, $spec);

	preg_match( '/(2~([\w\d\s\.]+))/', $specs, $matches);
	$memory = $matches[2];
	$spec = array(	'id'	=>	2,
			'value' =>	$matches[2]);
	array_push( $arrSpecs, $spec);

	preg_match( '/(3~([\w\d\s\.]+))/', $specs, $matches);
	$hdd = $matches[2];
	$spec = array(	'id'	=>	4,
			'value' =>	$matches[2]);
	array_push( $arrSpecs, $spec);

	preg_match( '/(4~([\w\d\s\.]+))/', $specs, $matches);
	$cd = $matches[2];
	$spec = array(	'id'	=>	6,
			'value' =>	$matches[2]);
	array_push( $arrSpecs, $spec);

	preg_match( '/(5~([\w\d\s\.]+))/', $specs, $matches);
	$dvd = $matches[2];
	$spec = array(	'id'	=>	7,
			'value' =>	$matches[2]);
	array_push( $arrSpecs, $spec);

	preg_match( '/(6~([\w\d\s\.]+))/', $specs, $matches);
	$soundcard = $matches[2];
	$spec = array(	'id'	=>	8,
			'value' =>	$matches[2]);
	array_push( $arrSpecs, $spec);

	preg_match( '/(7~([\w\d\s\.]+))/', $specs, $matches);
	$network = $matches[2];
	$spec = array(	'id'	=>	9,
			'value' =>	$matches[2]);
	array_push( $arrSpecs, $spec);

	preg_match( '/(8~([\w\d\s\.]+))/', $specs, $matches);
	$modem = $matches[2];
	$spec = array(	'id'	=>	10,
			'value' =>	$matches[2]);
	array_push( $arrSpecs, $spec);

	preg_match( '/(9~([\w\d\s\.]+))/', $specs, $matches);
	$os = $matches[2];
	$spec = array(	'id'	=>	3,
			'value' =>	$matches[2]);
	array_push( $arrSpecs, $spec);

	preg_match( '/(10~([\w\d\s\.]+))/', $specs, $matches);
	$other = $matches[2];
	$spec = array(	'id'	=>	24,
			'value' =>	$matches[2]);
	array_push( $arrSpecs, $spec);

#	print "<tr><td>$id</td>";
#	print "<td>" . $type . "</td>";
#	print "<td>" . $specs . "</td>";
#	print "<td>" . $processor . "</td>";
#	print "<td>" . $memory . "</td>";
#	print "<td>" . $hdd . "</td>";
#	print "<td>" . $cd . "</td>";
#	print "<td>" . $dvd . "</td>";
#	print "<td>" . $soundcard . "</td>";
#	print "<td>" . $network . "</td>";
#	print "<td>" . $modem . "</td>";
#	print "<td>" . $os. "</td>";
#	print "<td>" . $other . "</td>";
#	print "</tr>";

	while( $spec = array_pop( $arrSpecs)) {
		if( $spec[ value] <> '') {
			$sql_insert = "insert into tblEquipmentSpec (specID, equipmentID, value) values
					(" . $spec[ id] . ", " . $equipmentID . ", '" . mysql_escape_string( $spec[ value]) . "')";
			print $sql_insert . '<br>';
			mysql_query( $sql_insert);
		}
	}

	if( $processor . $memory . $hdd . $cd . $fdd . $dvd . $soundcard . $network . $modem . $os . $other <> '') {
		$sql_insert = "insert into tblEquipmentSpec (specID, equipmentID, value) values ()";
	}
	
}

mysql_close();

print '</table>';

?>
