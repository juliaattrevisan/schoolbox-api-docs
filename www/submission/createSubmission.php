<?php

require_once("../include/session.php");
require_once("../include/include.php");
require_once("../include/staffonly.php");
require_once("../include/html-time.php");
require_once('../include/syn_userinfo.php');
require_once('../include/involved.php');
require_once("../include/fileSecurity.php");
require_once("../cms/file.php");

require_once("subbox.php");

class CreateSubmissionPage {
	function CreateSubmissionPage()
	{
				$this->db = new dbManager;
                $this->t_body = new Template(TPL_directory);
               	$this->t_body->set_file("tplBODY", "submission/createSub.htm");		
				$this->t_body->set_block('tplBODY', 'browse_row', 'browse_block');
	}

	function setFileTypeList($type=0)
	{
		//get types of files that can be added
		$query = "SELECT * FROM file_type";
		$this->db->dbDoQuery($query);
		$typeHTML = "<OPTION VALUE=\"0\"></OPTION>";
		while($line = $this->db->dbGetNextArray())
		{
			if($line['id'] == $type)
			{
				$typeHTML .= "<OPTION VALUE=\"".$line['id']."\" selected>";
				$typeHTML .= $line['name'];
				$typeHTML .= "</OPTION>";
			}
			else
			{
				$typeHTML .= "<OPTION VALUE=\"".$line['id']."\">";
				$typeHTML .= $line['name'];
				$typeHTML .= "</OPTION>";
			}
		}
		$typeHTML = "<select name=\"type\" class=\"formfield\">".$typeHTML."</select>";
		$this->t_body->set_var("TYPELIST", $typeHTML);
	}	
	

	function setDateFields($modify=0)
	{
		global $subbox;

		//it selects todays date by default
		//added feature to allow it to set current date those pulled from database in modify situation
		if($modify)
		{
			$details = $subbox->getSubBoxDetails($modify);

			//convert times into timestamps
			$open_date = strtotime($details['open_date']);
			$due_date = strtotime($details['date']);
			$late_date = strtotime($details['late_date']);	
	
			$open_hour = date('g',$open_date);
			$open_min = date('i',$open_date);
			
			//output open dates
			$this->t_body->set_var("OPEN_DATE", date('d/m/Y', $open_date));
			$this->t_body->set_var("OPEN_HOUR", $open_hour);
			$this->t_body->set_var("OPEN_MIN", $open_min);
			if(date('a', $open_date) == 'pm')
				$this->t_body->set_var("OPEN_PM", "selected");
			else
				$this->t_body->set_var("OPEN_PM", "");
								
	
			$due_hour = date('g',$due_date);
			$due_min = date('i',$due_date);
			
			//output due dates
			$this->t_body->set_var("DUE_DATE", date('d/m/Y', $due_date));
			$this->t_body->set_var("DUE_HOUR", $due_hour);
			$this->t_body->set_var("DUE_MIN", $due_min);
			if(date('a', $due_date) == 'pm')
				$this->t_body->set_var("DUE_PM", "selected");
			else
				$this->t_body->set_var("DUE_PM", "");
			
			//output late dates
			$late_hour = date('g',$late_date);
			$late_min = date('i',$late_date);
			$this->t_body->set_var("LATE_DATE", date('d/m/Y', $late_date));
			$this->t_body->set_var("LATE_HOUR", $late_hour);
			$this->t_body->set_var("LATE_MIN", $late_min);
			if(date('a', $late_date) == 'pm')
				$this->t_body->set_var("LATE_PM", "selected");
			else
				$this->t_body->set_var("LATE_PM", "");
				
			$this->t_body->set_var("DESC", $details['description']);
			
		}
		else
		{
			//just set them to today + 1 hour
			//set variables in template
			$defaultduetime = strtotime('+1 hours');
			
			$this->t_body->set_var("OPEN_DATE", date('d/m/Y'));
			$this->t_body->set_var("OPEN_HOUR", date('g', $defaultduetime));
			$this->t_body->set_var("OPEN_MIN", '00');
			if(date('a', $defaultduetime) == 'pm')
				$this->t_body->set_var("OPEN_PM", "selected");
			else
				$this->t_body->set_var("OPEN_PM", "");
			
			
			if (is_numeric($_GET['Day']) && is_numeric($_GET['Month']) && is_numeric($_GET['Year']))	
				$dueDate = date('d/m/Y', mktime(0,0,0,$_GET['Month'],$_GET['Day'],$_GET['Year']));
			else
				$dueDate = date('d/m/Y');
			
			$this->t_body->set_var("DUE_DATE", $dueDate);
			$this->t_body->set_var("DUE_HOUR", date('g', $defaultduetime));
			$this->t_body->set_var("DUE_MIN", '00');
			if(date('a', $defaultduetime) == 'pm')
				$this->t_body->set_var("DUE_PM", "selected");
			else
				$this->t_body->set_var("DUE_PM", "");
			
			$this->t_body->set_var("LATE_DATE", $dueDate);
			$this->t_body->set_var("LATE_HOUR", date('g', $defaultduetime));
			$this->t_body->set_var("LATE_MIN", '00');
			if(date('a', $defaultduetime) == 'pm')
				$this->t_body->set_var("LATE_PM", "selected");
			else
				$this->t_body->set_var("LATE_PM", "");

		}

	}

	function setCategory($category)
	{
		if($category)
		{
			$query = "SELECT name, code FROM category WHERE id = '$category'";
			$result = $this->db->dbGetSingleRow($query);

			//if category has code output it
			if($result['code'])			
				$catOutput = "<b>".$result['name']." (".$result['code'].")</b>";
			else
				$catOutput = "<b>".$result['name']."</b>";

			$catOutput .= "<input type=\"hidden\" name=\"cid\" value=\"$category\">";

			$this->t_body->set_var("CATLIST", $catOutput);
		}
		else
			$this->setCategoryList();
		
	}

	function setCategoryList($category=0)
	{
		//convert category list into html code
		$this->listTree(0, "");
		array_shift($this->catList);

		$catHTML = "";
		while ($thisCat = array_shift($this->catList) ) 
		{
				if($thisCat['id'] == $category)
				{
					$catHTML .= "<OPTION VALUE=\"" . $thisCat['id'] . "\" selected>";
					$catHTML .= $thisCat['name'];
					if($thisCat['code'] != "")
						$catHTML .= " (".$thisCat['code'].")";
					$catHTML .= "</OPTION>";
				}
				else
				{
					$catHTML .= "<OPTION VALUE=\"" . $thisCat['id'] . "\">";
					$catHTML .= $thisCat['name'];
					if($thisCat['code'] != "")
						$catHTML .= " (".$thisCat['code'].")";
					$catHTML .= "</OPTION>";
				}
		}
		$catHTML = "<select name=\"cid\" class=\"formfield\">".$catHTML."</select>";
		$this->t_body->set_var("CATLIST", $catHTML);
		
	}
	
	function listTree($parent, $ident)
	{
		$this->catList[-1] = "";

		if (is_numeric($_GET['user']) && isStaff($_SESSION['gid'])) {
		
			if ($parent > 0)
				return true;
			
			// get student subjects
			$query = "SELECT synergy_id, group_id FROM user WHERE id = " . $_GET['user'];
			$user = $this->db->dbGetSingleRow($query);
			$sSubjects = getSubjectList($user['synergy_id'], $user['group_id']);
			$sCatList = involvedCategoryList($_GET['user'], $sSubjects, 1);
			
			// get teacher subjects
			$tSubjects = getSubjectList($_SESSION['sid'], $_SESSION['gid']);
			$tCatList = involvedCategoryList($_SESSION['id'], $tSubjects, 1);
			
			// intersect subjects
			$catList = array();
			foreach ($sCatList as $sCat) {
				if (in_array($sCat, $tCatList))
					$catList[] = $sCat;
			}
			
			
			$catQuery = "SELECT name, id, code FROM category ";
			if (is_array($catList)) {
				$catQuery .= " WHERE ( 1=2 ";
				foreach($catList as $cat) {
					$catQuery .= "OR id = {$cat['id']} ";
				}
				$catQuery .= ")";
			}
			$catQuery .= " ORDER BY name";

		}
		//query database for all categories that user has permission to see
		else if($_SESSION['su'])
			$catQuery = "SELECT name, id, code FROM category WHERE parent = '".$parent."' ORDER BY name";
		else
			$catQuery = "SELECT name, id, code FROM category, perms WHERE parent = '".$parent."' AND
                                cid=id AND gid ='".$_SESSION['gid']."' AND type >= '1' ORDER BY name";

 		$result = $this->db->dbDoQuery($catQuery);

		if( $this->db->dbCountRows() > 0 )
		{
			while($line = $this->db->dbGetNextArray($result) )
			{
				$thisRow['id'] = $line['id'];
				$thisRow['name'] = $ident.$line['name'];
				$thisRow['code'] = $line['code'];
				array_push($this->catList, $thisRow);

				$this->listTree($line['id'], $ident."&nbsp; &nbsp;|&nbsp;");
			}
		}
		$this->db->dbEndQuery($result);
	}	
	
	function showCreate($fileid=0, $cid=0)
	{
		$this->t_body->set_var("TITLE", HEADING." - Create Submission Box");
		$this->t_body->set_var("HEADING", "Create");
		$this->setDateFields();

		//set hidden fields so they get submitted with form
		//$this->t_body->set_var("FILEID", $fileid);
		$this->t_body->set_var("SID", "");
		
		//get file description to put into field
		$db = new dbManager;
		$db->dbConnect();
		$query = "SELECT description FROM file WHERE id = '$fileid'";
		$db->dbDoQuery($query);
		$file = $db->dbGetNextArray();
		$this->t_body->set_var("DESC", $file['description']);
		
		$this->setFileTypeList($_GET['type']);	
		$this->setCategory($_GET['category']);	
		
		if (is_numeric($_GET['fileid'])) {
			$this->t_body->set_var('FILEID', $_GET['fileid']);	
			$this->t_body->set_var('browse_block', '');	
		} else {
			$this->t_body->parse('browse_block', 'browse_row');	
		}
		
		
		$this->showPage();
	}
	
	function showModify($sid)
	{
		$this->t_body->set_var("TITLE", HEADING." - Modify Submission Box");
		$this->t_body->set_var("HEADING", "Modify");
		$this->setDateFields($sid);
		$this->t_body->set_var("SID", "<input type=\"hidden\" name=\"sid\" value=\"$sid\">");
		
		global $subbox;
		$details = $subbox->getSubBoxDetails($sid);
		$this->setFileTypeList($details['type']);
		$this->setCategory($details['cid']);
		
		$this->t_body->set_var('browse_block', '');	
				
		$this->showPage();	
	}
		
        function showPage()
        {

		include_once("../include/page.php");

                $this->t_body->pparse("finaloutput", "tplBODY");
        }
}

$myPage = new CreateSubmissionPage();
$subbox = new subBox;

if($_POST['due_date'] && $_POST['due_hour'] && $_POST['late_date'] && $_POST['late_hour'] && $_POST['sid'])
{
	//submission box id and times sent so update the submission box times
	$subbox->updateSubmissionDate($_POST['sid'], 
		$_POST['open_date'], $_POST['open_hour'], $_POST['open_minute'], $_POST['open_period'],
		$_POST['due_date'], $_POST['due_hour'], $_POST['due_min'], $_POST['due_period'], 
		$_POST['late_date'], $_POST['late_hour'], $_POST['late_min'], $_POST['late_period'], 
		$_POST['description'], $_POST['type']);
	localRedir("/submission/listSubmission.php");
}
else if($_POST['due_date'] && $_POST['due_hour'] && $_POST['late_date'] && $_POST['late_hour'])
{

	$fileId = $_POST['fileId'];
	$fileDB = new file; 
	//form has been submitted 
	if( isset($_FILES['file']['name']) && $_FILES['file']['name'] )
	{
		//call file security checks
		
		fileSecurityCheck($_FILES['file']['error'], $_FILES['file']['name'], $_FILES['file']['tmp_name'], $_FILES['file']['size'], $_FILES['file']['type']);

		
		
		
		$fileId = $fileDB->addFile($_FILES['file']['name'], $_FILES['file']['type'], $_FILES['file']['tmp_name'], $_POST['description'], 
											$_POST['comment'], $_POST['cid']);
		
		
	} else if (!is_numeric($_POST['fileId'])) {
		
		$fileId = $fileDB->addFile("", "", "", $_POST['description'], $_POST['comment'], $_POST['cid']);
	}
	
	
	$subbox->createSubmission($_POST['cid'], 
			$_POST['open_date'], $_POST['open_hour'], $_POST['open_minute'], $_POST['open_period'],
			$_POST['due_date'], $_POST['due_hour'], $_POST['due_minute'], $_POST['due_period'], 
			$_POST['late_date'], $_POST['late_hour'], $_POST['late_min'], $_POST['late_period'], 
			$fileId, $_POST['description'], $_POST['type']);
			
			


	localRedir("/submission/listSubmission.php");
	
}
else if(isset($_GET['category']) && isset($_GET['fileid']) && $_GET['category'] && $_GET['fileid'])
{
	//display create submission box page
	//have recieved values from file submission page 
	$myPage->showCreate($_GET['fileid'], $_GET['category']);
}
else
{
	// just show the page
	$myPage->showCreate();
} 

?>
