<?php
/************************************************************
*                                                           *
*         Copyright (c) 2002-2003 Lektec Consulting         *
*                                                           *
************************************************************/
//notifies the appropriate party of any new or modified detentions

//  !!! NOTE !!!  To change the email address of School Steward emails set the $this->ss value in createHeaders()

//  !!! NOTE !!! Initially, the code in createHeaders() to be used will be commented out and test email addresses used instead 
//		 These test addresses need to be deleted and the correct ones uncommented.
//		 As soon as these are amended, the system will notify students and staff accordingly 

class notify
{
	function notify($state, $detDate, $studID, $staffID, $offence, $workset, $type, $history)
	{
		//setup db
		$this->db = new Database("intranet");
		$this->db->dbConnect();
		//setup db for synergy queries
		//$this->msdb = mssql_connect(MSSQL_host, MSSQL_user, MSSQL_passwd);

		//set vars for detention details
		$this->date = $detDate['detdate'];
		$this->time = $detDate['time'];
		//detstaff is the staff member taking the detention
		$this->detstaff = $detDate['lastname'].", ".substr($detDate['firstname'], 0, 1);
		$this->location = $detDate['location'];
		$this->studID = $studID;
		//assStaff is the staff member who assigned the detention
		$this->assStaffID = $staffID;
		$this->offence = stripslashes($offence);
		$this->workset = stripslashes($workset);
		$this->history = stripslashes($history);

		//state represents which email format to use, ie. stud, HoH, SS, dup notifications, adding, modifying, confirming
		$this->state = $state;
		$this->getDetailsStudStaff();
		$this->getDetailsSS();
		
		$typearray = array(
			'R' => 'Red',
			'Y' => 'Yellow',
			'G' => 'Green',
			'Red' => 'Red',
			'Yellow' => 'Yellow',
			'Green' => 'Green'
		);

		$this->type = $typearray[$type];

		if ($state == "UC"){
			$this->getDetailsHoH();
			$this->createHoHEmail();
		}
		//else if MOD or ADD or DEL
		else {
			$this->createStudEmail();
		}
	}

	function mail()
	{
		$mail = mail($this->to, $this->subject, $this->message, $this->headers, $this->params); 

		return $mail;
	}

	function getDetailsStudStaff()
	{
		//get details of staff and student involved in this detention
		//set appropriate variables to use in email fields
		$query = "SELECT * FROM user
			  WHERE id IN ( '".$this->studID."', '".$this->assStaffID."' )
		";

		$this->db->query($query);
		$permType = permType();
		while ($ppl = $this->db->getNextArray()){
			if ($ppl['group_id'] == 1 || $ppl['group_id'] == 2 || 
				($ppl['id'] == $_SESSION['id'] && $permType == 1)){
				$this->assStaffName = $ppl['lastname'].", ".substr($ppl['firstname'], 0, 1);
				$this->assStaffUN = $ppl['username'];
			}
			else if ($ppl['group_id'] == 3 || $ppl['group_id'] == 4 || 
				($permType == 1 && $ppl['id'] != $_SESSION['id'])){
				$this->studName = $ppl['firstname']." ".$ppl['lastname'];
				$this->studYear = $ppl['year'];
				$this->studSynID = $ppl['synergy_id'];
				$this->studUN = $ppl['username'];
				$this->studHouse = $ppl['house'];
			}
		}
		if (!empty($this->studHouse)) {
			$query = "SELECT house FROM `house` WHERE `id` = ".$this->studHouse;
			$this->db->query($query);
			$house = $this->db->getNextArray();
			$this->studHouseName = $house['house'];
		}
	}

	function getDetailsHoH(){

		//get house details to find out HoH
		$this->HoHID = permHoH($this->studHouse);

		$query = "SELECT * FROM user
			  WHERE id = '".$this->HoHID."'
			";
		$this->db->query($query);
		$hoh = $this->db->getNextArray();
		$this->HoHname = $hoh['firstname']." ".$hoh['lastname'];
		$this->HoHUN = $hoh['username'];
	}
	
	function getDetailsSS()
	{
		//get school steward details
		$this->SSID = permSS();
		
		$query = "SELECT * FROM user
			  WHERE id = '".$this->SSID."'
			";
		$this->db->query($query);
		$ss = $this->db->getNextArray();
		$this->SSname = $ss['firstname']." ".$ss['lastname'];
		$this->SSUN = $ss['username'];
	}

	function createHeaders($recipient)
	{
		//set school steward as the return email address

//uncomment this section, CC to assigned staff means the staff member taking the detention
// the CC email may be unnecessary, leave commented out if preferred.
/**************************
		$ss = "schoolsteward";
		$cc = $this->assStaffUN;
**************************/

		$ss = "tascott";

//delete the $bcc and $ss values below for email notification to work properly

		$ss = $this->SSUN;

		// Robert Skillington 30 June 2005
		// Updating code to send the correct headers for HTML mail

		// To send HTML mail, the Content-type header must be set
		$this->headers  = "MIME-Version: 1.0\r\n";
		$this->headers .= "Content-type: text/html; charset=iso-8859-1\r\n";


		// Additional headers		
		$this->headers .= "From: School Steward <".$ss."@".MAIL_address.">\r\n";
		$this->headers .= "Reply-To: School Steward <".$ss."@".MAIL_address.">\r\n";

		//this is a hack at getting bounced messages to return to sender
		$this->params = "-f".$ss."@".MAIL_address;

// comment line below for the appropriate recipient to recieve email
//		$touname = "testaccount"; 

// uncomment the $touname assignments below (3 of them) to start using system properly
		if ($recipient == 'S'){
			$touname = $this->studUN;
			$this->to = $this->studName." <".$touname."@".MAIL_address.">";
		}
		else if ($recipient == 'H'){ 
			$touname = $this->HoHUN;
			$this->to = $this->HoHname." <".$touname."@".MAIL_address.">";
		}
		else if ($recipient == "SS"){
			$touname = $this->SSUN;
			$this->to = $this->SSname." <".$touname."@".MAIL_address.">";
		}
		else 
			error(57);
	}

	function setDetDetails()
	{
		$details = "	STUDENT DETAILS:\r\n
				ID: ".$this->studSynID."\r\n
				Name:\t".$this->studName."\r\n
				Year:\t".$this->studYear."\r\n\n
				A ".$this->type." card detention was issued to the above student on this day by ".$this->assStaffName." for the offence of ".$this->offence.".\r\n\n
				DETENTION DETAILS:\r\n
				When:\t".$this->date." ".$this->time."\r\n
				Where:\t".$this->location."\r\n
				Staff:\t".$this->detstaff."\r\n
		";
		//don't show the student the workset
		if ($this->state != "ADD" && $this->state != "MOD" && $this->state != "DEL")
			$details.="Workset:\t".$this->workset."\r\n\n\n";
		else
			$details.="\n\n";

		return $details;
	}

	function createStudEmail()
	{
	// Mark Parncutt 21 Jan 2005
	// The wording of this email has been changed after talking with Paris.

	// Also put a switch here instead of all the elseif, elseif, elseif, ...

		switch ($this->state) {
			case 'ADD':
				$blurb = "You have been issued a ".$this->type." card detention";
				break;
			case 'MOD':
				$blurb = "A detention given to you has been modified.  Please take care to note any changes made to your detention.";
				break;
			case 'DEL':
				$blurb = "Your ".$this->type." card detention has been cancelled.";
				break;
		}

		if ($this->state == "ADD" || $this->state == "MOD") {
			$this->subject = "Notification of Detention";
			$blurb2 = "<p>Report to ".$this->location." on ".$this->date.".</p><p>In the case of non-attendance, you will be issued with a harsher penalty.</p>";
		} else {
			$this->subject = "Cancellation of Detention";
			$blurb2 = "";
		}

$this->message = "<html>
  <body>
    <p>
      <strong>Attention:</strong> ".$this->studName."
    </p>
    <p>
      <strong>".$this->subject."</strong>
    </p>
    <table>
      <tr>
        <td colspan=2>STUDENT DETAILS:</td>
      </tr>
      <tr>
        <td><strong>Name:</strong></td>
        <td>".$this->studName."</td>
      </tr>
      <tr>
        <td><strong>Year:</strong></td>
        <td>".$this->studYear."</td>
      </tr>
      <tr>
        <td><strong>House:</strong></td>
        <td>".$this->studHouseName."</td>
      </tr>
    </table>
    <p>
      <font color=".strtolower($this->type).">
        <strong>".$blurb."</strong>
      </font>
    </p>
    <table>
      <tr>
        <td colspan=2>DETENTION DETAILS:</td>
      </tr>
      <tr>
        <td><strong>Issued by:</strong></td>
        <td>".$this->assStaffName."</td>
      </tr>
      <tr>
        <td><strong>Offence:</strong></td>
        <td>".$this->offence."</td>
      </tr>
      <tr>
        <td><strong>Work set: </strong></td>
        <td>".$this->workset."</td>
      </tr>
      <tr>
        <td colspan=2>&nbsp;</td>
      </tr>
      <tr>
        <td><strong>When:</strong></td>
        <td>".$this->date."</td>
      </tr>
      <tr>
        <td><strong>Where:</strong></td>
        <td>".$this->location."</td>
      </tr>
      <tr>
        <td><strong>Staff:</strong></td>
        <td>".$this->detstaff."</td>
      <tr>
    </table>
    ".$blurb2."
    <p>
      &nbsp;
    </p>
    <p>
      This is an automatically generated email.
    </p>
  </body>
</html>
";
		$this->createHeaders('S');
	}

	function createHoHEmail()
	{
		//set subject text
		$this->subject = "Notification to Confirm Detention"; 

		//set message body
		if ($this->state == "UC"){
			$this->message = "<html>
  <body>
    <p>
      <strong>Attention:</strong> ".$this->HoHname."
    </p>
    <p>
      <strong>Notification to Confirm Detention</strong>
    </p>
    <table>
      <tr>
        <td colspan=2>STUDENT DETAILS:</td>
      </tr>
      <tr>
        <td><strong>Name:</strong></td>
        <td>".$this->studName."</td>
      </tr>
      <tr>
        <td><strong>Year:</strong></td>
        <td>".$this->studYear."</td>
      </tr>
    </table>
    <p>
      <font color=".strtolower($this->type).">
        <strong>A ".$this->type." card detention has been issued to the above student.</strong>
      </font>
    </p>
    <table>
      <tr>
        <td colspan=2>DETENTION DETAILS:</td>
      </tr>
      <tr>
        <td><strong>Issued by:</strong></td>
        <td>".$this->assStaffName."</td>
      </tr>
      <tr>
        <td><strong>Offence:</strong></td>
        <td>".$this->offence."</td>
      </tr>
      <tr>
        <td><strong>Work set:</strong></td>
        <td>".$this->workset."</td>
      </tr>
	  <tr>
        <td><strong>Details:</strong></td>
        <td>".$this->history."</td>
      </tr>
      <tr>
        <td colspan=2>&nbsp;</td>
      </tr>
      <tr>
        <td><strong>When:</strong></td>
        <td>".$this->date."</td>
      </tr>
      <tr>
        <td><strong>Where:</strong></td>
        <td>".$this->location."</td>
      </tr>
      <tr>
        <td><strong>Staff:</strong></td>
        <td>".$this->detstaff."</td>
      <tr>
    </table>
    <p>
      As you are the Head of ".$this->studHouseName." House and the above student being a ".$this->studHouseName." House member,
      please speak to the student in regard to the aforementioned detention.
    </p>
    <p>
      Upon doing so, please visit the online Detention System and confirm this detention.
    </p>
    <p>
      &nbsp;
    </p>
    <p>
      This is an automatically generated email.<br>
      If you have any queries, please contact ".$this->SSname." &lt;<a href=mailto:".$this->SSUN."@".MAIL_address.">".$this->SSUN."@".MAIL_address."</a>&gt;
    </p>
  </body>
</html>
";
		}
		$this->createHeaders('H');
	}
	
	function createSSEmail()
	{
		//set message body
		if ($this->state == "DUP"){

			//set subject text
			$this->subject = "Notification of Multiple Detentions for a student";

			$this->message = " 
				Attention: ".$this->SSname."\r\n\n

				Notification of Multiple Detentions for ".$this->studName.":\r\n\n

				".$this->setDetDetails()."

				This student already has at least one current detention assigned to them.
				Please amend the detentions using the Detention Management System.\r\n
				
			";
		}
		else if ($this->state == "UC"){

			//set subject text
			$this->subject = "Notification to Confirm Detention"; 

			$this->message = "
				Attention: ".$this->SSname."\r\n\n

				Notification of Detention\r\n\n 
				".$this->setDetDetails()."

				As the School Steward, you are required to speak to the student in regards to the aforementioned detention.\r\n
				Upon doing so, please confirm or amend the detention using the Detention Management System.\r\n
				
			";	
		}
		else
			print "SS, incorrect state, no email sent";
		$this->createHeaders('SS');
	}	
}
?>
