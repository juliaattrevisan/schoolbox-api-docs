<?
//------------------------------------------------------
//Duties Management Form
//lwcawood
//------------------------------------------------------

require_once("../include/session.php");
require_once("../include/include.php");
require_once("../include/permission.php");


class editDuties
{	
	function editDuties($cat, $currYear=0, $currMonth=0, $currDay=0)
	{
		$this->category = $cat;
		$this->service = 1;
		$this->checkPerms();
		$this->db = new DeprecatedDatabase();
		$this->t_body = new Template(TPL_directory);
		if(!$currYear) { $this->currYear = date("Y"); } else { $this->currYear = $currYear; }
        if(!$currMonth) { $this->currMonth = date("n"); } else { $this->currMonth = $currMonth; }
        if(!$currDay) { $this->currDay = date("j"); } else { $this->currDay = $currDay; }
	}

        function checkPerms()
        {
                $this->perm = perm($this->category, $this->service);
                if($this->perm < 2)
                        error(14);
        }


	function doOutput ($message)
	{
		$dayOfWeek = date("w", mktime(0,0,0,$this->currMonth, $this->currDay, $this->currYear));
        $startOfWeek = mktime (0,0,0,$this->currMonth, ($this->currDay-$dayOfWeek), $this->currYear);

		$previousWeek = mktime (0,0,0,$this->currMonth, ($this->currDay-7), $this->currYear);
        $nextWeek = mktime (0,0,0,$this->currMonth, ($this->currDay+7), $this->currYear);
		$currentDate = mktime (0,0,0,$this->currMonth, $this->currDay, $this->currYear);
		$previousDay = mktime (0,0,0,$this->currMonth, ($this->currDay-1), $this->currYear);
        $nextDay = mktime (0,0,0,$this->currMonth, ($this->currDay+1), $this->currYear);
		
		$queryDate = date("Y-m-d", $currentDate); 


		$this->t_body->set_var("WEEKBACK", "editDuties.php?category=".$this->category."&Year=".date("Y",$previousWeek).
                                "&Month=".date("m",$previousWeek)."&Day=".date("d", $previousWeek));
        $this->t_body->set_var("WEEKFORWARD", "editDuties.php?category=".$this->category."&Year=".date("Y",$nextWeek).
                                "&Month=".date("m",$nextWeek)."&Day=".date("d", $nextWeek));
		$this->t_body->set_var("TODAY", "editDuties.php?category=".$this->category."&Year=".date("Y").
                                "&Month=".date("m")."&Day=".date("d"));								
		$this->t_body->set_var("DAYBACK", "editDuties.php?category=".$this->category."&Year=".date("Y",$previousDay).
                                "&Month=".date("m",$previousDay)."&Day=".date("d", $previousDay));
        $this->t_body->set_var("DAYFORWARD", "editDuties.php?category=".$this->category."&Year=".date("Y",$nextDay).
                                "&Month=".date("m",$nextDay)."&Day=".date("d", $nextDay));

		
		
		$this->t_body->set_file("tplBODY", "subjects/editDuties.htm");
		$this->t_body->set_block("tplBODY", "duty_list", "d_block");
		include_once("../include/page.php");
		//$sql = "SELECT * FROM duty where date = now() AND cid = ".$this->category ;
		$sql = "SELECT l.*, d.user, d.date, d.cid FROM luDuties l LEFT JOIN duty d ON l.dutyTypeID = d.dutyTypeID and d.date = '".$queryDate."' where l.cid = ".$this->category ;
		$this->db->dbDoQuery($sql);
		$i = 0;
		$this->t_body->set_var("DATE", date('l, jS \of F', $currentDate));
		$this->t_body->set_var("QDATE", $queryDate);
		$this->t_body->set_var("GET", "?category=".$this->category."&Year=".date("Y",$currentDate).
                                "&Month=".date("m",$currentDate)."&Day=".date("d", $currentDate) );
		$this->t_body->set_var("TITLE", HEADING." - Duty Editor");
		$this->t_body->set_var("CID", $this->category );
		while($row = $this->db->dbGetNextArray())
		{
			if($row['user'] == ""){$update = 'false';}else{$update = 'true';}
			
			$this->t_body->set_var("DUTYID", $row['dutyTypeID']);
			$this->t_body->set_var("I", $i);
			$this->t_body->set_var("UPDATE", $update);
			$this->t_body->set_var("DUTY", $row['name']);
			$this->t_body->set_var("ASSIGNEDUSER", $row['user']);
			$this->t_body->parse("d_block", "duty_list", true);
			$i++;
		}
		$this->t_body->set_var("MAXI", $i-1);	
		if($i == 0)
		{
			$this->t_body->set_var("d_block", '');
		}
		$this->t_body->pparse("finaloutput", "tplBODY");	
	}
	
	function doUpdate()
	{
		$queryDate = $_POST['qdate'];
		
		for($i=0;$i<=$_POST['maxi'];$i++)
		{
			$dutyTypeID = $_POST['dutyTypeID'.$i];
			$user = $_POST['user'.$i];
			$this->category = $_POST['cid'];
			$update = $_POST['update'.$i];
			if($user != "")
			{
				if($update == "true")
				{	
					$sql = "UPDATE duty SET user = '$user' 
						where cid = '".$this->category."' and date = '".$queryDate."' and dutyTypeID = '$dutyTypeID'";
					$this->db->dbDoQuery($sql);			
				}
				else
				{	
					$sql = "INSERT INTO duty(cid, date, dutyTypeID, user) VALUES('$this->category','$queryDate','$dutyTypeID','$user')";
					$this->db->dbDoQuery($sql);
				}				
			}
		}
	}
	
	function parseCSV() {
		if (!is_file($_FILES['file']['tmp_name']))
			return false;
			
		$handle = fopen($_FILES['file']['tmp_name'], "r");

		while (($data = fgetcsv($handle, 4000, ",")) !== FALSE) {
			
			list($day, $month, $year) = explode("/", $data[0]);
			if($day)
				$date = $year . '-' . $month . '-' . $day;
			else
				$date = '';
			
			
			$length = count($data);
			for ($j=0; $j < $length; $j++) {
				if ($date && !empty($data[$j+1])) {
					$sql = "SELECT dutyID FROM duty WHERE date = '" . $date . "' AND dutyTypeID = ".($j+1);
					$duty = $this->db->dbGetSingleRow($sql);
					if (isset($duty['dutyID'])) {
						$sql = "UPDATE duty SET ";
						$where = " WHERE dutyID = " . $duty['dutyID'];
					} else {
						$sql = "INSERT INTO duty SET ";
						$where = '';
					}
					$sql .= "cid = '".$this->category."', date = '" . $date . "', dutyTypeID = '" . ($j+1) . "', user = '" . $data[$j+1] . "'" . $where;
					$this->db->dbDoQuery($sql);
				}
			}
		}
		
		
		fclose($handle);
		localRedir('/subject/editDuties.php?category=' . $this->category .'&Year=' . $year . '&Month=' . $month . '&Day=' . $day);
		exit;
	
	}
}

$page = new editDuties($_GET['category'], $_GET['Year'], $_GET['Month'], $_GET['Day']);
if($_POST['Submit'] == 'Save')
	$page->doUpdate();
if($_FILES['file']['name'])
	$page->parseCSV();	


$page->doOutput($message);

?>
