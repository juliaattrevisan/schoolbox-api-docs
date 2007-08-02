<?php 

#update helpdeskdev.MGS_Equipment e
#set roomID =
#  (select locationID
#  from helpdesknew.tblLocation l
#  where l.description=e.room)

mysql_connect('localhost', 'helpdesk' , 'n00tbOOKS')
	or die( 'Couldn\'t connect to mysql.');
mysql_select_db( 'helpdeskdev');

$query = mysql_query( "
		select e.id, m.modelID as modelid from helpdeskdev.MGS_Equipment e 
                        inner join 
                        helpdesknew.tblModel m 
                        on (m.description = e.model)    
                        inner join
                        helpdesknew.tblBrand b
                        on (b.brandid = m.brandid)
                        where b.description = e.brand
		");


#$query = mysql_query( "select e.model, t.modelID from helpdeskdev.MGS_Equipment e
			#inner join
			#helpdesknew.tblModel t
			#on t.description = e.model");

#$query = mysql_query( 'select id, model from helpdesk.MGS_Equipment');

while( $result = mysql_fetch_array( $query)) {
	#$eq = $result[ 'modelID '];
	#$sql_update = "insert into helpdesknew.tblModel (brandID, description) values ('" . $result[ 'brandID'] . "', '" . $result[ 'model'] . "';";
	#$sql_update = "update helpdeskdev.MGS_Equipment set modelid='" . $result[ 'modelID'] . "' where id='" . $result[ 'id'] . "';";
	$sql_update = "update helpdeskdev.MGS_Equipment set modelid='" . $result[ 'modelid'] . "' where id='" . $result[ 'id'] . "';";
	print $sql_update . '<br>';
	#mysql_query( $sql_update);
}

mysql_close();

?>
