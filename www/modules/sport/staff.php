<?php

//showStaff object...gets all info required to populate template fields in staff.htm

require_once("../../include/include.php");
require_once("../../include/session.php");
require_once("../../include/permission.php");
require_once("../../include/html-time.php");
require_once("sql/modstaff.php");

class initStaff
{
	function initStaff($flag = "", $id = "")
	{
                $this->flag = $flag;
                $this->id = $id;

		switch($this->flag)
		{
			case 1:
        			//add staff
        			$this->initDisplay();
        			if (isset($_POST['lname']) && $_POST['lname'])
        			{
        		        	$myStaff = new staff();
        		       		if ($myStaff->addStaff($_POST['lname'], $_POST['fname'], $_POST['phone'], $_POST['email'])){
        	                		$this->displayAddStaff();
       		 	        	}
               	 			else
                        			print "could not add staff member";
        			}
        			else {
                			$this->displayAddStaff();
				}
				break;
			case 2:
				//save staff
        			$this->initDisplay();
       	 			if (isset($_POST['staffid']) && $_POST['staffid'])
        			{
                			$staffid = $_POST['staffid'];
                			$lname = $_POST['lname'];
       				        $fname = $_POST['fname'];
                			$phone = $_POST['phone'];
               	 			$email = $_POST['email'];
                			$num = 0;
                			foreach ($staffid as $stid)
                			{
                       				$myStaff = new staff($stid);
                        			$myStaff->modifyStaff($stid, $lname[$num], $fname[$num], $phone[$num], $email[$num]);
                        			$num++;
                			}
                			$this->displayStaff();
        			}
        			else {
                			$this->displayStaff();
        			}
				break;
			case 3:
 				//delete staff
        			$myStaff = new staff($id);
        			$myStaff->deleteStaff();
        			$this->initDisplay();
        			$this->displayStaff();
				break;
			default:
        			//display staff
        			$this->initDisplay();
        			$this->displayStaff();
				break;
		}

	}

	function initDisplay()
	{
		$this->t_body = new Template(TPL_directory);

		//setup file
		$this->t_body->set_file("tplBODY", "modules/sports/admin/staff.htm");
	 	//output page
		include_once("../../include/page.php");

		//setup blocks
		$this->t_body->set_block("tplBODY", "modstaff_body", "modstaff_block");
		$this->t_body->set_block("modstaff_body", "modstaff_list", "modstaff_listing");
		$this->t_body->set_block("tplBODY", "add_staff", "addstaff_block");

		$this->t_body->set_var("DISPLAY", $display);
		$this->t_body->set_var("NAV", "");
		$this->t_body->set_var("TITLE", "Sports Staff Administration");
	}

	function displayAddStaff()
	{
		//displays form to add a staff member and stores it in database
		$this->t_body->set_var("NAV", "<a href=\"staff.php\"><b>Back</b></a>");
		$this->t_body->set_var("DISPLAY", "Add Staff");
	 	$this->t_body->set_var("modstaff_block", "");
		$this->t_body->parse("addstaff_block", "add_staff", true);
		$this->t_body->pparse("finaloutput", "tplBODY");
	}

	function displayStaff()
	{
		//display list of staff to begin with
		$this->t_body->set_var("NAV", "<a href=\"staff.php?flag=1\"><b>Add Staff</b></a>");
		$this->t_body->set_var("DISPLAY", "Modify Staff");

		$myStaff = new staff();
		$staff = $myStaff->getStaff();
		if ($staff)
		{
			foreach ($staff as $info)
			{
				$this->t_body->set_var("STAFFID", $info['staffid']);
				$this->t_body->set_var("LNAME", $info['lname']);
				$this->t_body->set_var("FNAME", $info['fname']);
				$this->t_body->set_var("PHONE", $info['phone']);
				$this->t_body->set_var("EMAIL", $info['email']);
				$this->t_body->parse("modstaff_listing", "modstaff_list", true);
			}
		}
		$this->t_body->set_var("addstaff_block", "");
		$this->t_body->parse("modstaff_block", "modstaff_body", true);
		$this->t_body->pparse("finaloutput", "tplBODY");
	}
}
if (perm(1,3) > 0){

	$page = new initStaff($_REQUEST['flag'], $_REQUEST['id']);
}
else
	error(19);
?>
