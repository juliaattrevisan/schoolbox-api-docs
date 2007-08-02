#!/usr/local/bin/php

<?php

		#*****************************************************************************************
		# This sends a response to the person who sent the support request
		#*****************************************************************************************

	require_once( 'common.php');
	require_once( 'sql.php');
	
	emailResponse();

	function emailResponse()
	{
		$to = $person."@mgs.vic.edu.au";	#set the person to send email to

			$subject = "Task reminder from helpdesk";	# subject

				# message
				$message = "You have been added to a new task. Please check helpdesk<br>
							for more information";

		# To send HTML mail, you can set the Content-type header.
		$headers  = "MIME-Version: 1.0\r\n";
		$headers .= "Content-type: text/html; charset=iso-8859-1\r\n";

		/* additional headers */
		$headers .= "To: rsjones@mgs.vic.edu.au\r\n";		
		$headers .= "From: MGS Helpdesk <helpdesk@mgs.vic.edu.au>\r\n";

		/* and now mail it */
		mail($to, $subject, $message, $headers);
		}
	}

	mysql_close();
?>