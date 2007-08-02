#!/usr/local/bin/php

<?php

		#*****************************************************************************************
		# This gets the notebooks ready for pickup and sends an email to heads of house with a 
		# list of names of people who's laptop is ready for pickup
		#*****************************************************************************************

	require_once( 'common.php');
	require_once( 'sql.php');
	require_once( 'comsInterfaceResponse.php');
	
	function emailHouse()
	{
		$link = mysql_connect( DB_HOST, DB_USER, DB_PASSWD);
		mysql_select_db( DB_NAME);
		
		#Getting the user's name has a laptop ready in the last 24 hours
		$sql = "SELECT u.firstname, u.lastname, u.year, h.house FROM DB_USER_SYNERGY.user u, DB_USER_SYNERGY.house h, tblEquipment e, DB_NAME.tblJobEvent je, DB_NAME.tblJob j
				 WHERE je.jobID = j.jobID AND j.equipmentID = e.equipmentID AND e.userID = u.synergy_id AND u.house = h.id AND je.eventTypeID = 16 AND j.statusID <> 3";
		
		$result = mysql_query( $sql);
		print mysql_error($link);

		$i = 0;
	
		$message = "**The following notebooks are ready for collection: **<br>";
		
		while($row = mysql_fetch_array($result)) #while still reading in records
		{
			$name = $row['firstname'] . " " . $row['lastname'];
								
			$message .=	"Student Name: $name <br>
			Year:". $row['year']."<br>
			House:". $row['house']."<br><br>";
			
			$i++;
		}
		
		
		$to = HOH_EMAIL; //NEED TO INCLUDE THE CORRECT EMAIL

		$subject = "Notebooks ready for collection";	# subject
		
		emailResponse($to, $subject, $message); 
	}
	

	mysql_close();
?>