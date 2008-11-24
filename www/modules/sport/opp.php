<?php

//showOpp object...gets all info required to populate template fields in opp.htm

require_once("../../include/session.php");
require_once("../../include/include.php");
require_once("../../include/permission.php");
require_once("../../include/html-time.php");
require_once("sql/modopp.php");

class initOpp
{
	function initOpp($flag = "", $id = "")
	{
                $this->flag = $flag;
                $this->id = $id;
                switch ($this->flag){
			case 1:
				//add opp
				$this->initDisplay();
                                if (isset($_POST['opp']) && $_POST['opp'])
                                {
                                        $myOpp = new opp();
                                        if ($myOpp->addOpp($_POST['opp']))
                                                $this->displayAddOpp();
                                        else
                                                print "could not add opposition";
                                }
                                else   
                                        $this->displayAddOpp();
				break;
			case 2:
 				//save opposition
				$this->initDisplay();
                                if (isset($_POST['opp']) && $_POST['opp'])
                                {       
                                        $oid = $_POST['oid'];
                                        $opp = $_POST['opp'];
                                        $num = 0;
                                        foreach ($oid as $oppid)
                                        {
                                                $myOpp = new opp($oppid);
                                                $myOpp->modifyOpps($opp[$num]);
                                                $num++;
                                        }
                                        $this->displayOpp();
                                }
                                else
                                        $this->displayOpp();
				break;
			case 3:
				//delete opposition
                                $myOpp = new opp($this->id);
                                $myOpp->deleteOpp();
				$this->initDisplay();
                                $this->displayOpp();
				break;
			default:
 				//display opposition list
				$this->initDisplay();
				$this->initDisplay();
                                $this->displayOpp();
				break;
		}		
	}

	function initDisplay()
	{
		$this->t_body = new Template(TPL_directory);

		//setup file
		$this->t_body->set_file("tplBODY", "modules/sports/admin/opp.htm");
	 	//output page
		include_once("../../include/page.php");
	
		//setup blocks
		$this->t_body->set_block("tplBODY", "modopp_body", "modopp_block");
		$this->t_body->set_block("modopp_body", "modopp_list", "modopp_listing");
		$this->t_body->set_block("tplBODY", "add_opp", "addopp_block");

		$this->t_body->set_var("DISPLAY", $display);
		$this->t_body->set_var("NAV", "");
		$this->t_body->set_var("TITLE", "Sports Opposition Administration");
	}

	function displayAddOpp()
	{
		//displays form to add an opposition team and store it in database
		$this->t_body->set_var("NAV", "<a href=\"opp.php\"><b>Back</b></a>");
		$this->t_body->set_var("DISPLAY", "Add Opp");
	 	$this->t_body->set_var("modopp_block", "");	
		$this->t_body->parse("addopp_block", "add_opp", true);
		$this->t_body->pparse("finaloutput", "tplBODY"); 
	}


	function displayOpp()
	{
		//display list of opposition teams
		$this->t_body->set_var("NAV", "<a href=\"opp.php?flag=1\"><b>Add Opposition</b></a>");
		$this->t_body->set_var("DISPLAY", "Modify Oppositions");

		$myOpp = new opp();
		$opps = $myOpp->getOpp();		
		if ($opps){
			foreach ($opps as $opp) {	
				$this->t_body->set_var("OID", $opp['oid']);
				$this->t_body->set_var("OPP", $opp['opp']);
				$this->t_body->parse("modopp_listing", "modopp_list", true);	
			}
		}
		$this->t_body->set_var("addopp_block", "");
		$this->t_body->parse("modopp_block", "modopp_body", true);
		$this->t_body->pparse("finaloutput", "tplBODY"); 
	}
}
if (perm(1,3) > 0){

	$page = new initOpp($_REQUEST['flag'], $_REQUEST['id']);
}
else
	error(19);
?>
