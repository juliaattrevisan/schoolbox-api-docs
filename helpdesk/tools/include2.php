<?php 

//Synergy options
$time = gettimeofday();
$sec1 = $time[ 'sec'];

#mysql_connect( MSSQL_host, MSSQL_user, MSSQL_passwd)
mysql_connect('localhost', 'intranet' , 'Qu4LitY')
	or die( 'Couldn\'t connect to mysql.');
mysql_select_db( 'intranet');
#$query = mysql_query( "select * from user where synergy_id='49914'");
$query = mysql_query( "select * from user where lastname like '%doyle%'");

while( $result = mysql_fetch_array( $query)) {
	print_r( $result);
}

mysql_close();

$time = gettimeofday();
$sec2 = $time[ 'sec'];
print( "HOLA!$sec1 Took " . $sec2 - $sec1 . ' seconds.');

?>
