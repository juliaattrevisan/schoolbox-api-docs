<?
/*
rewite of notify.php
kids cant code
*/
require_once('../../../include/include.php');
require_once('../../../include/mailer.php');

define("NOTIFY_DEBUG", true); //just prints emails

class Notify
{
	function Notify($detID)
	{
		//constructer
		//gets the detention info
		$this->db = new Database('detention');
		$sql = "SELECT d.*, detdate.detdatetime, location.location, detstaff.firstname as detStaffFirstname, 
			detstaff.lastname as detStaffLastname, 	student.firstname as studentFirstname, 
			student.lastname as studentLastname, student.username as studentUsername, student.year as studentYear,
			giver.firstname as giverFirstname, giver.lastname as giverLastname, giver.username as giverUsername,
			offence.description as offenceDescription, status.type as statusType FROM det d
			LEFT JOIN detdate ON d.detDate = detdate.id
			LEFT JOIN location ON detdate.location = location.id
			LEFT JOIN ".SQL_db.".user detstaff ON detdate.staff = detstaff.id
			LEFT JOIN ".SQL_db.".user student ON d.studID = student.id
			LEFT JOIN ".SQL_db.".user giver ON d.staffID = giver.id
			LEFT JOIN offence ON d.offence = offence.id
			LEFT JOIN status ON d.status = status.id
			WHERE d.id = ".$detID;
		$this->db->query($sql);
		if($this->db->countRows())
		{
			$this->det = $this->db->getNextArray();
		}			
	}
	
	function getDetTypeName($det)
	{
			$typearray = array(
			'R' => 'Red',
			'Y' => 'Yellow',
			'G' => 'Green',
			'Red' => 'Red',
			'Yellow' => 'Yellow',
			'Green' => 'Green'
		);
		return $typearray[$det];
	}
	
	function sendCreateEmails()
	{
		//public function
		//sends Emails to student, issuer and hoh incase of reds
		$this->emailStudent("You have been issued a ".$this->getDetTypeName($this->det['type'])." card detention");
		$this->emailIssuer("");
		if($this->det['type'] == 'R')
			$this->emailHoH();

	}
	
	function sendModfiyEmails()
	{
		//public function
		//sends email to student notifying them of change to their detention
		$this->emailStudent("A detention given to you has been modified.  Please take care to note any changes made to your detention.");	
	}
	
	function sendCancelEmails()
	{
		//public function
		//sends email to student & issuer 
		$this->emailStudent("Your ".$this->getDetTypeName($this->det['type'])." card detention has been cancelled.");
		$this->emailIssuer("");
	}
	
	function emailStudent($message)
	{
		//private function
		//emails student relevent action
		$mail = new Mailer('modules/detention/mail/studEmail.htm', "Detention System");
		$mail->setVar("STUDNAME", $this->det['studentFirstname'].' '.$this->det['studentLastname']);
		$mail->setVar("SUBJECT", '');
		$mail->setVar("STUDYEAR", $this->det['studentYear']);
		$mail->setVar("STUDHOUSE", '');
		$mail->setVar("BLURB", $message);
		$mail->setVar("ASSSTAFFNAME", $this->det['giverFirstname'].' '.$this->det['giverLastname']);
		$mail->setVar("OFFENCE", $this->det['offenceDescription']);
		$mail->setVar("WORKSET", $this->det['workset']);
		$mail->setVar("DETDATE", $this->det['detdatetime']);
		$mail->setVar("DETLOC", $this->det['location']);
		$mail->setVar("DETSTAFFNAME", $this->det['detStaffFirstname'].' '.$this->det['detStaffLastname']);
		$mail->setVar("BLURB2", "");
		if(NOTIFY_DEBUG)
			$this->debugEmail($this->det['studentUsername'].'@'.Cfg::get('mail_address'), $mail->composeHTML());
		else
			$mail->send($this->det['studentUsername'].Cfg::get('mail_address'));
	}
	
	function emailIssuer($message)
	{
		//private function
		//emails issuer relevent action
		$mail = new Mailer('modules/detention/mail/giverEmail.htm', "Detention System");
		$mail->setVar("STUDNAME", $this->det['studentFirstname'].' '.$this->det['studentLastname']);
		$mail->setVar("SUBJECT", '');
		$mail->setVar("GIVERNAME", $this->det['giverFirstname'].' '.$this->det['giverLastname']);
		$mail->setVar("STUDYEAR", $this->det['studentYear']);
		$mail->setVar("STUDHOUSE", '');
		$mail->setVar("BLURB", $message);
		$mail->setVar("ASSSTAFFNAME", $this->det['giverFirstname'].' '.$this->det['giverLastname']);
		$mail->setVar("OFFENCE", $this->det['offenceDescription']);
		$mail->setVar("WORKSET", $this->det['workset']);
		$mail->setVar("DETDATE", $this->det['detdatetime']);
		$mail->setVar("DETLOC", $this->det['location']);
		$mail->setVar("DETSTAFFNAME", $this->det['detStaffFirstname'].' '.$this->det['detStaffLastname']);
		$mail->setVar("BLURB2", "");		
		
		
		
		if(NOTIFY_DEBUG)
			$this->debugEmail($this->det['giverUsername'].'@'.Cfg::get('mail_address'), $mail->composeHTML());
		else
			$mail->send($this->det['giverUsername'].Cfg::get('mail_address'));
		
	}
	
	function emailHoH()
	{
		//private function
		//emails hoh relevent action
		$mail = new Mailer('modules/detention/mail/hohEmail.htm', "Detention System");
		$mail->setVar("STUDNAME", $this->det['studentFirstname'].' '.$this->det['studentLastname']);
		$mail->setVar("SUBJECT", '');
		$mail->setVar("STUDYEAR", $this->det['studentYear']);
		$mail->setVar("STUDHOUSE", '');
		$mail->setVar("BLURB", $message);
		$mail->setVar("ASSSTAFFNAME", $this->det['giverFirstname'].' '.$this->det['giverLastname']);
		$mail->setVar("OFFENCE", $this->det['offenceDescription']);
		$mail->setVar("WORKSET", $this->det['workset']);
		$mail->setVar("DETDATE", $this->det['detdatetime']);
		$mail->setVar("DETLOC", $this->det['location']);
		$mail->setVar("DETSTAFFNAME", $this->det['detStaffFirstname'].' '.$this->det['detStaffLastname']);
		$mail->setVar("BLURB2", "");		
		
		
		
		if(NOTIFY_DEBUG)
			$this->debugEmail("", $mail->composeHTML());
		else
			$mail->send($this->det['studentUsername'].Cfg::get('mail_address'));
		
	}
	
	function debugEmail($to, $email)
	{
		echo "-------------- To:$to. --------------<br />".$email;
	}
	
}

$page = new Notify($_GET['id']);
$page->sendCreateEmails();

?>