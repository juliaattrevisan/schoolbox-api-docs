<?php 

//Synergy options
define ("MSSQL_host","mgsadmindb");
define ("MSSQL_db", "SynergyOne");
define ("MSSQL_user","intranet");
define ("MSSQL_passwd","Qu4LitY");  

$time = gettimeofday();
$sec1 = $time[ 'sec'];

#mssql_connect( MSSQL_host, MSSQL_user, MSSQL_passwd)
mssql_pconnect( MSSQL_host, MSSQL_user, MSSQL_passwd)
	or die( 'Couldn\'t connect to synergy.');
mssql_select_db( MSSQL_db);
#$query = mssql_query( "select StudentSurname, StudentPreferred from vStudentsAll where StudentID='990' and currentsemesteronlyflag=1");
$query = mssql_query( "select StudentSurname, StudentPreferred from vStudentsAll where StudentSurname like '%doyle%'");
if ($query != 1){
while( $result = mssql_fetch_array( $query)) {
	print_r( $result);
}
}
mssql_close();

$time = gettimeofday();
$sec2 = $time[ 'sec'];
print( "HOLA!$sec1 Took " . $sec2 - $sec1 . ' seconds.');

?>
