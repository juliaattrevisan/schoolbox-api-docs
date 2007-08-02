#!/usr/local/bin/php

<?php

	require_once( 'common.php');
	require_once( 'sql.php');

	$link = mysql_connect( DB_HOST, DB_USER, DB_PASSWD);
	mysql_select_db( DB_NAME);

	twenty4hours();
	forty8hours();

		#*****************************************************************************************
		# This sends and email to the staff member after 24 hours as an alert, then one to both 
		# head of IT and staff member as an alert after 48 hours
		#*****************************************************************************************

	function twenty4hours()
	{
		$link = mysql_connect( DB_HOST, DB_USER, DB_PASSWD);
		mysql_select_db( DB_NAME);
		
		#Getting the user's name who has a job longer than 24 hrs but less than 48
		$sql = "SELECT DB_USER_PORTAL.user.username, date_format(DB_NAME.tblJob.createdDate, '%W, %D %M %Y') as cDate, date_format(DB_NAME.tblJob.dueDate, '%W %M %Y') as dDate, DB_NAME.tblJob.jobID, DB_NAME.tblJob.description, UNIX_TIMESTAMP(DB_NAME.tblJob.createdDate) as unix_time24 from DB_NAME.tblJob, DB_USER_PORTAL.user WHERE  DB_NAME.tblJob.assignedUserID = DB_USER_PORTAL.user.synergy_id AND DB_NAME.tblJob.statusID = 2 AND (UNIX_TIMESTAMP(now()) - UNIX_TIMESTAMP(DB_NAME.tblJob.createdDate) <= 5184000) AND (UNIX_TIMESTAMP(now()) - UNIX_TIMESTAMP(DB_NAME.tblJob.createdDate) < 10368000)";
		
		$result = mysql_query( $sql);
		print mysql_error($link);
		
		$person = "";	#initialise person

		while($row = mysql_fetch_array($result)) #while still reading in records
		{
			$person = $row['username'];	#get the person to send email to
			$desc = $row['description'];
			$start = $row['cDate'];
			$due = $row['dDate'];
			$job = $row['jobID'];	#get the job associated to that user

			$to = $person."@mgs.vic.edu.au";	#set the person to send email to

			$subject = "Task reminder from helpdesk";	# subject

				# message
				$message = "**The following task has taken longer than 24 hours: **<br>
							Task description: $desc<br>
							Task ID: $jobID<br>
							Assigned to user: $person<br>
							Task created on: $start<br>
							Task due: $due<br>";

		# To send HTML mail, you can set the Content-type header.
		$headers  = "MIME-Version: 1.0\r\n";
		$headers .= "Content-type: text/html; charset=iso-8859-1\r\n";

		/* additional headers */
		$headers .= "To: " .$to. "\r\n";		
		$headers .= "From: MGS Helpdesk <helpdesk@mgs.vic.edu.au>\r\n";
		/* and now mail it */
		mail($to, $subject, $message, $headers);
		}
	}
	
	
	
	function forty8hours()
	{
		$link = mysql_connect( DB_HOST, DB_USER, DB_PASSWD);
		mysql_select_db( DB_NAME);
		
		//description, jobID, assigned to, created date, due date
		
		#Getting the user's name who has a job longer than 24 hrs but less than 48
		$sql = "SELECT DB_USER_PORTAL.user.username, date_format(helpjohn.tblJob.createdDate, '%W, %D %M %Y'), date_format(helpjohn.tblJob.dueDate, '%W %M %Y'), helpjohn.tblJob.jobID, helpjohn.tblJob.description, UNIX_TIMESTAMP(helpjohn.tblJob.createdDate) as unix_time24 from helpjohn.tblJob, DB_USER_PORTAL.user WHERE  helpjohn.tblJob.assignedUserID = DB_USER_PORTAL.user.synergy_id AND helpjohn.tblJob.statusID = 2 AND (UNIX_TIMESTAMP(now()) - UNIX_TIMESTAMP(helpjohn.tblJob.createdDate) >= 10368000)";
		
		$result = mysql_query( $sql);
		print mysql_error($link);
		
		$person = "";	#initialise person

		while($row = mysql_fetch_array($result)) #while still reading in records
		{
			$person = $row['username'];	#get the person to send email to
			$desc = $row['description'];
			$start = $row['createdDate'];
			$due = $row['dueDate'];
			$job = $row['jobID'];	#get the job associated to that user
		
			$to = $person."@mgs.vic.edu.au";	#set the person to send email to

			$subject = "Task reminder from helpdesk";	# subject

				# message
				$message = "**The following task has taken longer than 48 hours: **<br>
							Task description: $desc<br>
							Task ID: $jobID<br>
							Assigned to user: $person<br>
							Task created on: $start<br>
							Task due: $due<br>";

		# To send HTML mail, you can set the Content-type header.
		$headers  = "MIME-Version: 1.0\r\n";
		$headers .= "Content-type: text/html; charset=iso-8859-1\r\n";

		/* additional headers */
		$headers .= "To: " .$to. "\r\n";		
		$headers .= "From: MGS Helpdesk <helpdesk@mgs.vic.edu.au>\r\n";
		$headers .= "Bcc: Sue Lines <sclines@mgs.vic.edu.au>\r\n";

		/* and now mail it */
		mail($to, $subject, $message, $headers);
		}
	}

	mysql_close();
?>