<?php 

//require_once('../include/sql.php');

mysql_connect('localhost', 'helpdesk' , 'n00tbOOKS')
	or die( 'Couldn\'t connect to mysql.');
mysql_select_db( 'helpdesknew');

$query = mysql_query("update tblJobEvent set latest = 0");

$query1 = mysql_query("select t1.* from tblJobEvent t1 
			left join tblJobEvent t2 
			on t1.jobID = t2.jobID 
			and t1.createdDate < t2.createdDate 
			where t2.jobID IS NULL 
			order by t1.jobID, t1.createdDate");

print mysql_num_rows($query1);
while( $result = mysql_fetch_array($query1)) 
{
	print $result['jobID'].", ".$result['eventTypeID'].", ".$result['jobEventID'].", ".$result['createdDate'].", ".$result['latest'];
 	if ($result['eventTypeID'] <> NULL)
		mysql_query("update tblJob set latestEvent = ".$result['eventTypeID']." where jobID = ".$result['jobID']) 
                   or die( 'Error setting latest event for ' . $result['jobID'] . ' to ' . $result['eventTypeID'] . ' :' . mysql_error());
	print "latest Event = ".$result['eventTypeID']." jobID = ".$result['jobID']."<br>";
}

mysql_close();
?>
