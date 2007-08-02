<?php
		#*****************************************************************************************
		# This sends a response to the person who sent the support request
		#*****************************************************************************************

	require_once( 'template.php' );
	require_once( 'common.php' );


	function emailResponse($to, $subject, $message)
	{

		$tpl = new Template(TPL_DIR);
		$tpl->set_file('tplContent', 'email.html');
		
		$tpl->set_var( 'CONTENT', $message);
		$tpl->set_var( 'TITLE', 'MGS Helpdesk - Automated Response' );
		$message = $tpl->parse('output', 'tplContent');
		
		/*$to = $person."@mgs.vic.edu.au";	#set the person to send email to

			$subject = "Task reminder from helpdesk";	# subject

				# message
				$message = "Your support request has been added to the system, <br>
							your head of house will be notified when your request has<br>
							been completed";
		*/

		# To send HTML mail, you can set the Content-type header.
		$headers  = "MIME-Version: 1.0\r\n";
		$headers .= "Content-type: text/html; charset=iso-8859-1\r\n";

		/* additional headers */		
		$headers .= "From: MGS Helpdesk <helpdesk@mgs.vic.edu.au>\r\n";

		/* and now mail it */
		mail($to, $subject, $message, $headers);
	}
?>