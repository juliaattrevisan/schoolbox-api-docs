<?php
//------------------------------------------------------
//Duties Management Form
//lwcawood
//------------------------------------------------------


require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/SitePage.php';

class editDuties
{
	function editDuties($cat, $currYear=0, $currMonth=0, $currDay=0)
	{
		$this->category = $cat;
		$this->service = 1;

		$this->db = DeprecatedDatabase::getInstance();
		$this->page = new SitePage();
        $this->checkPerms();
		if(!$currYear) { $this->currYear = date("Y"); } else { $this->currYear = $currYear; }
        if(!$currMonth) { $this->currMonth = date("n"); } else { $this->currMonth = $currMonth; }
        if(!$currDay) { $this->currDay = date("j"); } else { $this->currDay = $currDay; }
	}

    function checkPerms()
    {
        $groupHandler = new Group($this->service);
        $this->perm = $groupHandler->getPermission($this->category);
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

        $this->page->setFile(TEMPLATE_content,'modules/duties/editDuties.html');
		$this->page->setBlock(TEMPLATE_content, "duty_list", "d_block");
        $this->page->setTitle('Edit Duties');

		$this->page->setVar("WEEKBACK", "editDuties.php?category=".$this->category."&Year=".date("Y",$previousWeek).
                                "&Month=".date("m",$previousWeek)."&Day=".date("d", $previousWeek));
        $this->page->setVar("WEEKFORWARD", "editDuties.php?category=".$this->category."&Year=".date("Y",$nextWeek).
                                "&Month=".date("m",$nextWeek)."&Day=".date("d", $nextWeek));
		$this->page->setVar("TODAY", "editDuties.php?category=".$this->category."&Year=".date("Y").
                                "&Month=".date("m")."&Day=".date("d"));
		$this->page->setVar("DAYBACK", "editDuties.php?category=".$this->category."&Year=".date("Y",$previousDay).
                                "&Month=".date("m",$previousDay)."&Day=".date("d", $previousDay));
        $this->page->setVar("DAYFORWARD", "editDuties.php?category=".$this->category."&Year=".date("Y",$nextDay).
                                "&Month=".date("m",$nextDay)."&Day=".date("d", $nextDay));




		//$sql = "SELECT * FROM duty where date = now() AND cid = ".$this->category ;
		$sql = "SELECT l.*, d.user, d.date, d.cid FROM luDuties l LEFT JOIN duty d ON l.dutyTypeID = d.dutyTypeID and d.date = '".$queryDate."' where l.cid = ".$this->category ;
		$this->db->dbDoQuery($sql);
		$i = 0;
		$this->page->setVar("DATE", date('l, jS \of F', $currentDate));
		$this->page->setVar("QDATE", $queryDate);
		$this->page->setVar("GET", "?category=".$this->category."&Year=".date("Y",$currentDate).
                                "&Month=".date("m",$currentDate)."&Day=".date("d", $currentDate) );
		$this->page->setVar("CID", $this->category );
		while($row = $this->db->dbGetNextArray())
		{
			if($row['user'] == ""){$update = 'false';}else{$update = 'true';}

			$this->page->setVar("DUTYID", $row['dutyTypeID']);
			$this->page->setVar("I", $i);
			$this->page->setVar("UPDATE", $update);
			$this->page->setVar("DUTY", $row['name']);
			$this->page->setVar("ASSIGNEDUSER", $row['user']);
			$this->page->parse("d_block", "duty_list", true);
			$i++;
		}
		$this->page->setVar("MAXI", $i-1);
		if($i == 0)
		{
			$this->page->setVar("d_block", '');
		}
		$this->page->outputPage();
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

		SitePage::localRedir('/subject/editDuties.php?category=' . $this->category .'&Year=' . $year . '&Month=' . $month . '&Day=' . $day);
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
