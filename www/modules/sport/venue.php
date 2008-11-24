<?php

//showVenue object...gets all info required to populate template fields in venue.htm

require_once("../../include/session.php");
require_once("../../include/include.php");
require_once("../../include/permission.php");
require_once("../../include/html-time.php");
require_once("sql/modvenue.php");

class initVenue
{
	function initVenue($flag = "", $id = "")
	{
                $this->flag = $flag;
                $this->id = $id;

		switch($this->flag)
		{
			case 1:
 				//add venue
				$this->initDisplay();
                                if (isset($_POST['venue']) && $_POST['venue'])
                                {   
                                        $myVenue = new venue();
                                        if ($myVenue->addVenue($_POST['venue'], $_POST['melref']))
                                                $this->displayAddVenue();
                                        else
                                                print "could not add venue";
                                }
                                else   
                                        $this->displayAddVenue();
				break;
			case 2:
 				//save venues          
				$this->initDisplay();
                                if (isset($_POST['vid']) && $_POST['vid'])
                                {
                                        $vid = $_POST['vid'];
                                        $venue = $_POST['venue'];
                                        $melref = $_POST['melref'];
                                        $num = 0;
                                        foreach ($vid as $venid)
                                        {
                                                $myVenue = new venue($venid);
                                                $myVenue->modifyVenues($venid, $venue[$num], $melref[$num]);
                                                $num++;
                                        }
                                        $this->displayVenues();
                                }
                                else
                                        $this->displayVenues();
				break;
			case 3:
  				//delete venue
                                $myVenue = new venue($this->id);
                                $myVenue->deleteVenue();
				$this->initDisplay();
                                $this->displayVenues();
				break;
			default:
 				//display venue list
				$this->initDisplay();
                                $this->displayVenues();
				break;
		}
	}
	function initDisplay()
	{
		$this->t_body = new Template(TPL_directory);

		//setup file
		$this->t_body->set_file("tplBODY", "modules/sports/admin/venue.htm");
	 	//output page
		include_once("../../include/page.php");
	
		//setup blocks
		$this->t_body->set_block("tplBODY", "modvenues_body", "modvenues_block");
		$this->t_body->set_block("modvenues_body", "modvenues_list", "modvenues_listing");
		$this->t_body->set_block("tplBODY", "add_venue", "addvenue_block");

		$this->t_body->set_var("DISPLAY", $display);
		$this->t_body->set_var("NAV", "");
		$this->t_body->set_var("TITLE", "Sports Venue Administration");
	}

	function displayAddVenue()
	{
		//displays form to add a venue and store it in database
		$this->t_body->set_var("NAV", "<a href=\"venue.php\"><b>Back</b></a>");
		$this->t_body->set_var("DISPLAY", "Add Venue");
	 	$this->t_body->set_var("modvenues_block", "");	
		$this->t_body->parse("addvenue_block", "add_venue", true);
		$this->t_body->pparse("finaloutput", "tplBODY"); 
	}
	
	function displayVenues()
	{
		//display list of venues
		$this->t_body->set_var("NAV", "<a href=\"venue.php?flag=1\"><b>Add Venue</b></a>");
		$this->t_body->set_var("DISPLAY", "Modify Venues");

		$myVenue = new venue();
		$venues = $myVenue->getVenues();		
		if ($venues)
		{
			foreach ($venues as $venue)
			{
				$this->t_body->set_var("VID", $venue['vid']);
				$this->t_body->set_var("VENUE", $venue['venue']);
				$this->t_body->set_var("MELREF", $venue['melref']);
				$this->t_body->parse("modvenues_listing", "modvenues_list", true);	
			}
		}
		$this->t_body->set_var("addvenue_block", "");
		$this->t_body->parse("modvenues_block", "modvenues_body", true);
		$this->t_body->pparse("finaloutput", "tplBODY"); 
	}
}
if (perm(1,3) > 0){

	$page = new initVenue($_REQUEST['flag'], $_REQUEST['id']);
}
else
	error(19);

?>
