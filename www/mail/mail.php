<?php
/************************************************************
*                                                           *
*         Copyright (c) 2002-2003 Lektec Consulting         *
*                                                           *
************************************************************/
//mail object.. controls all attributes of mail processing

class Mail
{

	function Mail()
	{}
  
	function checkMailDetails()
	{
		//check user has submitted their mail account details, if not request that they do
	
		$db = new dbManager();
	
		$query = "SELECT username, email_pass, local_email FROM user WHERE id = ".$_SESSION["id"];
		$info = $db->getRow($query);

		if($info["username"] == "" || $info["email_pass"] == "" || $info['local_email'] == 0) 
		{
				//failed to get information about user
				return false;
	
		}
		else
		{       //get all the info out
				$this->user = $info["username"];
				$this->mail_host = Cfg::get('mail_host');
				$this->password = $info["email_pass"];
				return true;
		} 
	}

	function submitMailDetails()
	{
		//redirect the user to the submit mail details form
		localRedir("/mail/mailInfo.php");
	}

	function openMailBox()
	{

		if(MAIL_retrieval == "imap")
			$this->server="{".$this->mail_host.":143/imap/notls/norsh}INBOX";
		else
			$this->server="{".$this->mail_host.":110/pop3}INBOX";
		
		$inbox = @imap_open ($this->server, $this->user, $this->password);
		$this->inbox = $inbox;
		return $inbox;
	}
	
	function mailboxStatus($inbox){
		if($status =  imap_status($inbox, $this->server, SA_ALL) )
			return $status;
		else
			print "imap failed: ".imap_last_error();	
	
	}

	function mailExists($msgid, $inbox)
	{
		if($msgid > 0 && $msgid <= imap_num_msg($inbox))
			return 1;
		else
			return 0;
	}

	function purgeMailBox()
	{
		//delete all email in mailbox
		
		//open mailbox
		$inbox = $this->openMailBox();
	
		$total = imap_num_msg($inbox);
	
		// delete all messages
		for($x=1; $x<$total; $x++)
		{
			if($this->mailExists($x, $inbox))
				imap_delete($inbox, $x);
		}
	
		//clean up
		imap_close($inbox, CL_EXPUNGE);
		//reload the page.. 
		localRedir("/mail.php");
	}


	function deleteMailArray($deleteArray)
	{
		//open mailbox
		$inbox = $this->openMailBox();
		// delete specified message numbers
		for($x=0; $x<sizeof($deleteArray); $x++)
		{
			if($this->mailExists($deleteArray[$x], $inbox)) {
				imap_delete($inbox, $deleteArray[$x]);
			}
		}
	
		//clean up
		imap_expunge($inbox);
		imap_close($inbox, CL_EXPUNGE);
		//reload the page.. 
		localRedir("/mail.php");
	}
	
	function deleteMailSingle($delete)
	{
		//open
		$inbox = $this->openMailBox();
		if($this->mailExists($delete, $inbox))
		{
			imap_delete($inbox, $delete);
			$total = imap_num_msg($inbox);
			imap_close($inbox, CL_EXPUNGE);
			//now return a sane email number to display or 0
			if($delete == $total)
				$delete--;
			//leave it up to caller to redirect
			return $delete;
		}
		else
			error(46);
	}

}

?>
