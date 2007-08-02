<?php 

mysql_connect('localhost', 'helpdesk' , 'n00tbOOKS')
	or die( 'Couldn\'t connect to mysql.');
mysql_select_db( 'helpdeskdev');
#$query = mysql_query( "select * from user where synergy_id='49914'");
$query = mysql_query( "select * from MGS_Equipment where room is null");

while( $result = mysql_fetch_array( $query)) {
	$location = $result[ 'location'];
	unset( $matches);
	preg_match( '/[WSG].*?\/([\w\d ]*)/', $location, $matches);
	if( is_array( $matches)) {
		$room = $matches[1];
		if( $room <> '') {
			$room = ucfirst( strtolower( $room));
			#print_r( $matches[1]);
			$sql_update = "update MGS_Equipment set room='" . $room . "' where id='" . $result[ 'id'] . "';";
			print $sql_update . '<br>';
		}
	}
}

mysql_close();

?>
