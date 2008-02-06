<?php
session_start();

require_once($_SERVER['DOCUMENT_ROOT']."/include/include.php");

class page {
	
	function page() {
		
		$userid = $_SESSION['id'];
		
		$this->db = new dbManager();
		$this->db->dbConnect();
		
		$no_user = $_REQUEST['no_user'];
		$enqum =$_REQUEST['enqum'];
		$enqu =$_REQUEST['enqu'];
		$fname =$_REQUEST['fname'];
		$femail =$_REQUEST['femail'];

		$this->t_body = new Template(TPL_directory);
		
		if($enqum =="ns" && $no_user =="yes") {
			$this->t_body->set_file("tplBODY", "modules/enquiry_point/indexn.htm");
			$rid1 ="mn";
			include("include.php");
		}

		if($enqum =="ns" && $no_user ==null) {
			$this->t_body->set_file("tplBODY", "modules/enquiry_point/index.htm");
			$rid1 ="mn";
			include("include.php");
		}
		
		if($fname ==NULL && $no_user =="yes") {
			$this->t_body->set_file("tplBODY", "modules/enquiry_point/indexn.htm");
			$rid2 ="fnn";
			include("include.php");
		}

		if($femail ==NULL && $no_user =="yes") {	
			$this->t_body->set_file("tplBODY", "modules/enquiry_point/indexn.htm");
			$rid3 ="fen";
			include("include.php");
		}

		if($enqu =="" && $no_user =="yes") {
			$this->t_body->set_file("tplBODY", "modules/enquiry_point/indexn.htm");
			$rid4 ="tn";
			include("include.php");
		}

		if($enqu =="" && $no_user ==NULL) {
			$this->t_body->set_file("tplBODY", "modules/enquiry_point/index.htm");
			$rid4 ="tn";
			include("include.php");
		}


		if($enqum !="ns" && $enqu !=NULL && $no_user ==NULL || $fname !=NULL && $femail !=NULL && $enqum !="ns" && $enqu !=NULL) {
				$this->t_body->set_file("tplBODY", "modules/enquiry_point/enqu2.htm");
				$allvalues ="yes";
		}
		
		$this->t_body->set_var("TITLE", HEADING." - Enquiry Point");
		
		$this->userdata($userid, $allvalues);

		$this->display();
		
	}

	function userdata($userid, $allvalues) {
		$sql ="SELECT firstname, lastname, username, year, group_id, userCampus.campus, alt_email FROM intranet.user LEFT JOIN userCampus ON 
uid = id 
WHERE id = 
'$userid' ORDER BY defaultCampus DESC";
		
		$row = $this->db->dbGetSingleRow($sql);

		$this->t_body->set_var($row);
		
		$yearlevel =$row['year'];
		$firstname =$row['firstname'];
		$lastname =$row['lastname'];
		$username =$row['username'];
		$campusid =$row['campus'];
		$groupid =$row['group_id'];
		
		if($row['alt_email'] !=="") {
			$emailad =$row[alt_email];
			$this->t_body->set_var("EMAIL", $row['alt_email']);
		}
		else {
			$emailad =$row['username']."@mgs.vic.edu.au";
			$this->t_body->set_var("EMAIL", $emailad);
		}

		if($campusid !==null) {
			$this->group($houseid, $campusid, $yearlevel, $firstname, $lastname, $username, $groupid, $allvalues);

		}
		else
		{
			$fname ="$_REQUEST[fname]";
			$emailfromuv ="$_REQUEST[femail]";
			$enqu =$_REQUEST[enqu];
			$user_verifyed ="not";
			$house ="";
			$campus ="";
			$yearlevel ="";
			$firstname ="";
			$lastname ="";
			$username ="";
			$group ="";
			$this->mailf($house, $campus, $yearlevel, $firstname, $lastname, $username, $group, $fname, $emailfromuv, $enqu, $user_verifyed, $allvalues);
		}
		

	}

	function group($houseid, $campusid, $yearlevel, $firstname, $lastname, $username, $groupid, $allvalues) {
		$sql ="SELECT * FROM intranet.groups WHERE id = '$groupid'";
		
		$row = $this->db->dbGetSingleRow($sql);

		$this->t_body->set_var($row);
		$group =$row[name];
		$this->house($houseid, $campusid, $yearlevel, $firstname, $lastname, $username, $group, $allvalues);	
	}

	function house($houseid, $campusid, $yearlevel, $firstname, $lastname, $username, $group, $allvalues) {
		$sql ="SELECT * FROM intranet.house WHERE id = '$houseid'";
		
		$row = $this->db->dbGetSingleRow($sql);

		$this->t_body->set_var($row);
		$house =$row[house];
		$this->campus($house, $campusid, $yearlevel, $firstname, $lastname, $username, $group, $allvalues);	
	}

	function campus($house, $campusid, $yearlevel, $firstname, $lastname, $username, $group, $allvalues) {
		$sql ="SELECT * FROM intranet.luCampus WHERE id = '$campusid'";
		$row = $this->db->dbGetSingleRow($sql);

		$this->t_body->set_var($row);
		$campus =$row[name];
		$fname ="";
		$emailfrom ="";
		$enqu ="";
		$user_verifyed ="yes";
		$this->mailf($house, $campus, $yearlevel, $firstname, $lastname, $username, $group, $fname, $emailfromuv, $enqu, $user_verifyed, $allvalues);
	}

	function mailf($house, $campus, $yearlevel, $firstname, $lastname, $username, $group, $fname, $emailfromuv, $enqu, $user_verifyed, $allvalues) {
		$enqum =$_REQUEST[enqum];
		$enqu1 =$_REQUEST[enqu];
		$enqu =nl2br("$enqu1");
		$fullname ="$firstname $lastname";

    		if($enqum =="pf")
		    {
		     $type ="Portal Feedback";
		     $name ="Portal Support";

			if($campus == "Wadhurst")
				$emailto = "Marc Bishop <mrbishop@mgs.vic.edu.au>, Jennifer Pratten <jcpratten@mgs.vic.edu.au>";
			else
		     		$emailto = "Portal Support <portal_support@mgs.vic.edu.au>";

		     $typefq ="feedback";
		    }
		    elseif($enqum =="gf")
		    {
		     $type ="General Feedback";
		     $emailto ="Portal Support <portal_support@mgs.vic.edu.au>";
		     $typefq ="feedback";
		    }
		    elseif($enqum =="tf")
		    {
		     $type ="Technical Feedback";
			if($campus =="Wadhurst") {
				$emailto ="Marc Bishop <mrbishop@mgs.vic.edu.au>, Jennifer Pratten <jcpratten@mgs.vic.edu.au>";
			}
			elseif($campus =="Grimwade") {
				$emailto ="Alex Trajkovski <aztrajkovski@mgs.vic.edu.au>";
			}
			else
			{		
				$emailto ="Helpdesk <helpdesk@mgs.vic.edu.au>";
			}

		     $typefq ="enquiry";
		    }
		    elseif($enqum =="se")
		    {
		     $type ="School Enquiries";
		     $emailto ="Reception <reception@mgs.vic.edu.au>";
		     $typefq ="enquiry";
		    }
		
		$this->t_body->set_var("TYPEFQ", "$typefq"); //sets word feedback/support under thank you
		
		if($user_verifyed ==not) {
			$not =not;
		}
		
		if($user_verifyed ==NULL) {
			$user_verifyed ="yes";
		}
	
		$portalform ="This email has been generated by the portal. User details have <b>$not</b> been verified.";

		if($user_verifyed ==yes && $group =="Grimwade Students" || $group =="Wadhurst Students" || $group =="Senior Students" && $allvalues ==yes) {	
			$emailfrom ="$username@mgs.vic.edu.au";
			$userdetails ="<b>Name:</b> $fullname <br> <b>Email:</b> $emailfrom<br> <b>Year Level:</b> $yearlevel<br> <b>Campus:</b> $campus<br> <b>User Type:</b> $group";
			$message ="$portalform<br><br><b>User Details:</b><br>$userdetails<br><br><b>$type:</b><br>$enqu";
		}
		elseif($user_verifyed ==yes && $allvalues ==yes) {
			$emailfrom ="$username@mgs.vic.edu.au";
			$userdetails ="<b>Name:</b> $fullname <br> <b>Email:</b> $emailfrom<br> <b>Campus:</b> $campus<br> <b>User Type:</b> $group";
			$message ="$portalform<br><br><b>User Details:</b><br>$userdetails<br><br><b>$type:</b><br>$enqu";		
		}
		elseif($user_verifyed ==not && $allvalues ==yes) {
			$emailfrom ="$emailfromuv";
			$fullname ="$fname";
			$userdetails ="<b>Name:</b> $fullname <br> <b>Email:</b> $emailfrom<br><b>User Type:</b> Unknown";
			$message ="$portalform<br><br><b>User Details:</b><br>$userdetails<br><br><b>$type:</b><br>$enqu";		
		}
		else {
			$name ="Helpdesk";
			$emailto ="helpdesk@mgs.vic.edu.au";
			$type ="error report";
			$message ="enquiry point has errored";
			$fullname ="form";
			//$emailfrom ="talawson@mgs.vic.edu.au";
		}
	
	
	
		$htmls = 'MIME-Version: 1.0' . "\r\n";
		$htmls .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n";
		mail($emailto, "Feedback Form - $type", "<font face=Arial size=2>$message</font>", $htmls."From:$fullname <$emailfrom>");
	}

	function display() {
		$this->t_body->pparse("finaloutput", "tplBODY");
	}

}


$page =new page();
