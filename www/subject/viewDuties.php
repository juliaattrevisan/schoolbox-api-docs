<?
//------------------------------------------------------
//Duties Management Form
//lwcawood
//------------------------------------------------------

require_once("../include/session.php");
require_once("../include/include.php");
//require_once("../include/permission.php");


class editDuties
{
	function editDuties($cat, $currYear=0, $currMonth=0, $currDay=0)
	{
		$this->category = $cat;
		$this->service = 1;
		$this->checkPerms();
		$this->db = DeprecatedDatabase::getInstance();
		$this->t_body = new Template(TPL_directory);
		if(!$currYear) { $this->currYear = date("Y"); } else { $this->currYear = $currYear; }
        	if(!$currMonth) { $this->currMonth = date("n"); } else { $this->currMonth = $currMonth; }
        	if(!$currDay) { $this->currDay = date("j"); } else { $this->currDay = $currDay; }
	}

        function checkPerms()
        {
                $this->perm = perm($this->category, $this->service);
                if($this->perm < 1)
                        error(14);
        }



	function doOutput ($message)
	{
		$dayOfWeek = date("w", mktime(0,0,0,$this->currMonth, $this->currDay, $this->currYear));
        	$startOfWeek = mktime (0,0,0,$this->currMonth, ($this->currDay-$dayOfWeek), $this->currYear);
		$previousWeek = mktime (0,0,0,$this->currMonth, ($this->currDay-7), $this->currYear);
        	$nextWeek = mktime (0,0,0,$this->currMonth, ($this->currDay+7), $this->currYear);
		$currentDate = mktime (0,0,0,$this->currMonth, $this->currDay, $this->currYear);


		$this->t_body->set_var("WEEKBACK", "viewDuties.php?category=".$this->category."&Year=".date("Y",$previousWeek).
                                "&Month=".date("m",$previousWeek)."&Day=".date("d", $previousWeek));
        	$this->t_body->set_var("WEEKFORWARD", "viewDuties.php?category=".$this->category."&Year=".date("Y",$nextWeek).
                                "&Month=".date("m",$nextWeek)."&Day=".date("d", $nextWeek));
		$this->t_body->set_var("TODAY", "viewDuties.php?category=".$this->category."&Year=".date("Y").
                                "&Month=".date("m")."&Day=".date("d"));



		$this->t_body->set_file("tplBODY", "subjects/viewDuties.htm");
		$this->t_body->set_block("tplBODY", "duty_list", "d_block");
		include_once("../include/page.php");
		//$userArray = new Array();
		//$dutiesList = new Array();
		for($j=0;$j<7;$j++)
		{
			$queryDate = date("Y-m-d", $startOfWeek + ($j*86400) );
                        $sql = "SELECT l.*, d.user, d.date, d.cid FROM luDuties l LEFT JOIN duty d ON l.dutyTypeID = d.dutyTypeID and d.date = '".
                                $queryDate."' where l.cid = ".$this->category ;
                        $this->db->query($sql);

			while($row = $this->db->getNextArray())
			{
				$dutiesList[$row['dutyTypeID']] = $row['name'];
				$userArray[$row['dutyTypeID']][$j] = $row['user'];
			}
			$this->t_body->set_var("DAYHEADING".$j, date("l, jS",$startOfWeek + ($j*86400)));
			$this->t_body->set_var("YEAR".$j, date("Y",$startOfWeek + ($j*86400)));
			$this->t_body->set_var("MONTH".$j, date("m",$startOfWeek + ($j*86400)));
			$this->t_body->set_var("DAY".$j, date("d",$startOfWeek + ($j*86400)));
		}

			$this->t_body->set_var("DATE", date('l, jS of F', $startOfWeek));
			$this->t_body->set_var("QDATE", $queryDate);
			$this->t_body->set_var("GET", "?category=".$this->category."&Year=".date("Y",$currentDate).
                                "&Month=".date("m",$currentDate)."&Day=".date("d", $currentDate) );
			$this->t_body->set_var("TITLE", HEADING." - View Duties");
			$this->t_body->set_var("CID", $this->category );

			if($this->perm < 2)
			{
				$this->t_body->set_block("tplBODY", "duty_edit", "d_edit");
				$this->t_body->set_block("d_edit", "");
			}

			foreach($userArray as $id => $user)
			{
				$this->t_body->set_var("DUTY", $dutiesList[$id]);
				$this->t_body->set_var("ASSIGNEDUSER0", $user[0]);
				$this->t_body->set_var("ASSIGNEDUSER1", $user[1]);
				$this->t_body->set_var("ASSIGNEDUSER2", $user[2]);
				$this->t_body->set_var("ASSIGNEDUSER3", $user[3]);
				$this->t_body->set_var("ASSIGNEDUSER4", $user[4]);
				$this->t_body->set_var("ASSIGNEDUSER5", $user[5]);
				$this->t_body->set_var("ASSIGNEDUSER6", $user[6]);

				$this->t_body->parse("d_block", "duty_list", true);
			}
			//if($i == 0)
			//{
			//	$this->t_body->set_var("d_block".$j, '');
			//}

		$this->t_body->pparse("finaloutput", "tplBODY");
	}

}

$page = new editDuties($_GET['category'], $_GET['Year'], $_GET['Month'], $_GET['Day']);
if($_POST['Submit'] == 'Save')
	$page->doUpdate();
$page->doOutput($message);

?>
