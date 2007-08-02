#!/usr/local/bin/php

<?php

	require_once( 'common.php');
	require_once( 'sql.php');

	$link = mysql_connect( DB_HOST, DB_USER, DB_PASSWD);
	mysql_select_db( DB_NAME);

	twenty4hours();
	forty8hours();

	function twenty4hours()
	{
		#*****************************************************************************************
		# Need to get the user names of all people with an overdue job and the job this relates to
		# For each person in the list, send them an email that relates to that job
		#*****************************************************************************************
		
		$link = mysql_connect( DB_HOST, DB_USER, DB_PASSWD);
		mysql_select_db( DB_NAME);
		
		//description, jobID, assigned to, created date, due date
		
		#Getting the user's name who has a job longer than 24 hrs but less than 48
		$sql = "SELECT intranet.user.username, date_format(helpjohn.tblJob.createdDate, '%W, %D %M %Y') as cDate, date_format(helpjohn.tblJob.dueDate, '%W %M %Y') as dDate, helpjohn.tblJob.jobID, helpjohn.tblJob.description, UNIX_TIMESTAMP(helpjohn.tblJob.createdDate) as unix_time24 from helpjohn.tblJob, intranet.user WHERE  helpjohn.tblJob.assignedUserID = intranet.user.synergy_id AND helpjohn.tblJob.statusID = 2 AND (UNIX_TIMESTAMP(now()) - UNIX_TIMESTAMP(helpjohn.tblJob.createdDate) <= 5184000) AND (UNIX_TIMESTAMP(now()) - UNIX_TIMESTAMP(helpjohn.tblJob.createdDate) < 10368000)";
		
		$result = mysql_query( $sql);
		print mysql_error($link);
		
		#Want to loop through each user returned and send them an e-mail about that job
		
		$person = "";	#initialise person

		while($row = mysql_fetch_array($result)) #while still reading in records
		{
		
	//		print_r($row); # Print the number of rows
			$person = $row['username'];	#get the person to send email to
			$desc = $row['description'];
			$start = $row['cDate'];
			$due = $row['dDate'];
			$job = $row['jobID'];	#get the job associated to that user

//			$to = $person."@mgs.vic.edu.au";	#set the person to send email to
		
			$to = $person."@mgs.vic.edu.au";

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
		
		#*****************************************************************************************
		# Need to get the user names of all people with an overdue job and the job this relates to
		# For each person in the list, send them an email that relates to that job
		#*****************************************************************************************
		
		$link = mysql_connect( DB_HOST, DB_USER, DB_PASSWD);
		mysql_select_db( DB_NAME);
		
		//description, jobID, assigned to, created date, due date
		
		#Getting the user's name who has a job longer than 24 hrs but less than 48
		$sql = "SELECT intranet.user.username, date_format(helpjohn.tblJob.createdDate, '%W, %D %M %Y'), date_format(helpjohn.tblJob.dueDate, '%W %M %Y'), helpjohn.tblJob.jobID, helpjohn.tblJob.description, UNIX_TIMESTAMP(helpjohn.tblJob.createdDate) as unix_time24 from helpjohn.tblJob, intranet.user WHERE  helpjohn.tblJob.assignedUserID = intranet.user.synergy_id AND helpjohn.tblJob.statusID = 2 AND (UNIX_TIMESTAMP(now()) - UNIX_TIMESTAMP(helpjohn.tblJob.createdDate) >= 10368000)";
		
		$result = mysql_query( $sql);
		print mysql_error($link);
		
		#Want to loop through each user returned and send them an e-mail about that job
		
		$person = "";	#initialise person

		while($row = mysql_fetch_array($result)) #while still reading in records
		{
		
	//		print_r($row); # Print the number of rows
			$person = $row['username'];	#get the person to send email to
			$desc = $row['description'];
			$start = $row['createdDate'];
			$due = $row['dueDate'];
			$job = $row['jobID'];	#get the job associated to that user

//			$to = $person."@mgs.vic.edu.au";	#set the person to send email to
		
			$to = $person."@mgs.vic.edu.au";

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