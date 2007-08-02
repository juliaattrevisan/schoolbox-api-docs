<?

require_once( 'include/helpDeskPage.php');
require_once("include/dbManager-mysql.php");

class reportTaskStat
{
	var $db;
	var $lu;
	var $tpl;
	
	function reportTaskStat()
	{
		$this->tpl = new Module(TPL_DIR);
		$this->tpl->set_file('tplContent', 'reportTaskStatus.html');
		$this->tpl->set_block('tplContent','person', 'personList');
		$this->db = new dbManager(DB_NAME, 1); 
		$this->db->dbConnect();
		$this->page = new HelpDeskPage;
		$this->page->authCheck(3);
	}
	
	function displayPage($begin, $end)
	{
		if($begin && $end)
		{
			$this->begin = $this->convertDateToMysql($begin);
			$this->end = $this->convertDateToMysql($end);
		}
		else
		{
			$this->begin = date('Y-m-d', mktime(0, 0, 0, date("m")  , date("d")-7, date("Y")));
			$this->end = date('Y-m-d');
		}
		
		$this->tpl->set_var("BEGIN_DATE", $this->convertDateFromMysql($this->begin)); 
		$this->tpl->set_var("END_DATE", $this->convertDateFromMysql($this->end)); 
		
		$this->outputUsers();
		$this->outputAll();
		
		$content = $this->tpl->parse("finaloutput", "tplContent");
		$this->page->setContent($content);
		$this->page->setTitle("Task Statistics Report");
		$this->page->printPage();
	}

	function convertDateToMysql($date)
	{
		list($date, $month, $year) = explode('/', $date);
		return "$year-$month-$date";
	}

	function convertDateFromMysql($date)
	{
		list($year, $month, $date) = explode('-', $date);
		return "$date/$month/$year"; 
	}

	function convertSecToTime($secs)
	{
		$days = floor($secs / 86400);
		$hours = floor(($secs%86400) / 3600);
		$minutes = floor(($secs%3600)/60);
		
		if($days)
			return "$days days $hours:$minutes";
			
		return $hours.':'.$minutes;
	}

	function outputAll()
	{
		//query total jobs started
		$queryStarted = "SELECT COUNT(*) as started FROM tblJobEvent 
		WHERE eventTypeID = ".DB_ID_EVENT_JOB_CREATED." AND 
		(createdDate >= '{$this->begin}' AND createdDate <= '{$this->end}')";
		$this->db->dbDoQuery($queryStarted);
		$started = $this->db->dbGetNextArray();
		
		//query total jobs closed
		$queryClosed = "SELECT COUNT(*) as closed FROM tblJobEvent 
		WHERE eventTypeID = ".DB_ID_EVENT_JOB_COMPLETED." AND 
		(createdDate >= '{$this->begin}' AND createdDate <= '{$this->end}')";
		
		$this->db->dbDoQuery($queryClosed);
		$closed = $this->db->dbGetNextArray();
		
		//query total jobs open
		$queryOpen = "SELECT COUNT(*) as open FROM tblJob 
		WHERE statusID != ".DB_ID_STATUS_CLOSED;
		
		$this->db->dbDoQuery($queryOpen);
		$open = $this->db->dbGetNextArray();
		
		//query avg completion time
		$queryTime = "SELECT (AVG(UNIX_TIMESTAMP(b.createdDate) - UNIX_TIMESTAMP(a.createdDate))) as time
		FROM tblJobEvent a RIGHT JOIN tblJobEvent b ON a.jobID = b.jobID RIGHT JOIN intranet.user c ON b.portalUserID = c.id 
		WHERE a.eventTypeID = 28 AND b.eventTypeID = 27 AND (b.createdDate >= '{$this->begin}' AND b.createdDate <= '{$this->end}')";
		
		$this->db->dbDoQuery($queryTime);
		$time = $this->db->dbGetNextArray();
		
		$this->tpl->set_var("NAME", "Total");
		$this->tpl->set_var("TASK_STARTED", $started['started']);
		$this->tpl->set_var("TASK_COMPLETED", $closed['closed']);
		$this->tpl->set_var("TASK_REMAINING", $open['open']);
		$this->tpl->set_var("TASK_TIME", $this->convertSecToTime($time['time']));
		
		$this->tpl->parse('personList', 'person', true);
	}
	
	function outputUsers()
	{
		$queryUsers = "SELECT firstname, lastname, portalID FROM tblStaff JOIN intranet.user u ON tblStaff.portalID = u.id";
		$result = $this->db->dbDoQuery($queryUsers);
		while($user = $this->db->dbGetNextArray($result))
		{
					//query total jobs started
			$queryStarted = "SELECT COUNT(*) as started FROM tblJobEvent 
			WHERE eventTypeID = ".DB_ID_EVENT_JOB_CREATED." AND 
			(createdDate >= '{$this->begin}' AND createdDate <= '{$this->end}') AND
			portalUserID = {$user['portalID']}";
			$this->db->dbDoQuery($queryStarted);
			$started = $this->db->dbGetNextArray();
			
			//query total jobs closed
			$queryClosed = "SELECT COUNT(*) as closed FROM tblJobEvent 
			WHERE eventTypeID = ".DB_ID_EVENT_JOB_COMPLETED." AND 
			(createdDate >= '{$this->begin}' AND createdDate <= '{$this->end}') AND
			portalUserID = {$user['portalID']}";
			
			$this->db->dbDoQuery($queryClosed);
			$closed = $this->db->dbGetNextArray();
			
			//query total jobs open
			$queryOpen = "SELECT COUNT(*) as open FROM tblJob 
			WHERE statusID != ".DB_ID_STATUS_CLOSED." AND 
			portalAssignedUserID = {$user['portalID']}";
			
			$this->db->dbDoQuery($queryOpen);
			$open = $this->db->dbGetNextArray();
			
			//query avg completion time
			$queryTime = "SELECT (AVG(UNIX_TIMESTAMP(b.createdDate) - UNIX_TIMESTAMP(a.createdDate))) as time
			FROM tblJobEvent a RIGHT JOIN tblJobEvent b ON a.jobID = b.jobID RIGHT JOIN intranet.user c ON b.portalUserID = c.id 
			WHERE a.eventTypeID = 28 AND b.eventTypeID = 27 AND (b.createdDate >= '{$this->begin}' AND b.createdDate <= '{$this->end}') 							
			AND b.portalUserID = {$user['portalID']}";
			
			$this->db->dbDoQuery($queryTime);
			$time = $this->db->dbGetNextArray();
				
			$this->tpl->set_var("NAME", $user['firstname'].' '.$user['lastname']);
			$this->tpl->set_var("TASK_STARTED", $started['started']);
			$this->tpl->set_var("TASK_COMPLETED", $closed['closed']);
			$this->tpl->set_var("TASK_REMAINING", $open['open']);
			$this->tpl->set_var("TASK_TIME", $this->convertSecToTime($time['time']));
			$this->tpl->parse('personList', 'person', true);
		}
	
	}	

	
	
}//end class

$page = new reportTaskStat();
$page->displayPage($_GET['beginDate'], $_GET['endDate']);


?>
