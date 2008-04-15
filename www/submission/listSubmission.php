<?php

require_once("../include/session.php");
require_once("../include/include.php");
require_once("../include/staffonly.php");

class ListSubmissionPage {
	function ListSubmissionPage()
	{
		$this->t_body = new Template(TPL_directory);
               	$this->t_body->set_file("tplBODY", "submission/listSub.htm");
		$this->t_body->set_var("TITLE", HEADING." - Submission Boxes");
		$this->t_body->set_block("tplBODY", "submission_block", "submission_listing");		
		$this->t_body->set_block("tplBODY", "category_block", "category_listing");
		
		include_once("../include/page.php");
	}
	
	function outputSubmission($id, $description, $open_date, $date, $marked=0, $total=0, $type = '')
	{
		if($marked=="")
			$marked = 0;
		$this->t_body->set_var("ID", $id);
		$this->t_body->set_var("ASSDESC", $description);
		$this->t_body->set_var("TYPE", $type);
		$this->t_body->set_var("JAVAASSDESC", addslashes($description));
		$this->t_body->set_var("OPENDATE", $open_date);
		$this->t_body->set_var("DUEDATE", $date);
		$this->t_body->set_var("MARKED", $marked);
		//$this->t_body->set_var("UNMARKED", ($total-$marked));
		$this->t_body->set_var("TOTAL", $total);

		$this->t_body->parse("submission_listing","submission_block",true);
	}

	function setSubmissions()
	{
		$db = new dbManager;
		$db->dbConnect();
	
		$currentCat = "";

		$listquery = "SELECT submission_box.id, submission_box.description, category.id as catid, category.code, category.name,
				DATE_FORMAT(submission_box.date, '%a, %D %b %l:%i%p') AS date, DATE_FORMAT(submission_box.open_date, '%a, %D %b %l:%i%p') AS open_date, 
				submission_box.type, file_type.name as type FROM category, submission_box LEFT JOIN file_type ON submission_box.type = file_type.id WHERE 
				category.id = submission_box.category AND submission_box.owner = '".$_SESSION['id']."' 
				AND submission_box.deleted = '0' ORDER BY category.id, submission_box.date"; 
	
		$result=$db->query($listquery);

		if($db->countRows() > 0)
		{
			$line = $db->getNextArray($result);
			$prevCat = $line;

			do
			{
				if($prevCat['catid'] != $line['catid'])
				{

					if($prevCat['code'] != "")
						$this->t_body->set_var("CATEGORY", $prevCat['name']." (".$prevCat['code'].")");
					else
						$this->t_body->set_var("CATEGORY", $prevCat['name']);

					$this->t_body->parse("category_listing", "category_block", true);
					$this->t_body->set_var("submission_listing", "");

				}

				//for each box we want to make some running totals of whats in them
				$totalquery = "SELECT COUNT(*) AS total, SUM(marked) AS marked FROM student_submission WHERE box_id = '".$line['id']."'"; 
				$db->query($totalquery);
				$totals = $db->getNextArray();

				$this->outputSubmission($line['id'],$line['description'], $line['open_date'], $line['date'], 
					$totals['marked'], $totals['total'], $line['type']);

				$prevCat = $line;

			}while($line = $db->getNextArray($result) );


			if($prevCat['code'] != "")
				$this->t_body->set_var("CATEGORY", $prevCat['name']." (".$prevCat['code'].")");
			else
				$this->t_body->set_var("CATEGORY", $prevCat['name']);

			$this->t_body->parse("category_listing", "category_block", true);

		}
		else
			$this->t_body->set_var("category_listing", "<br><b>Currently no active submission boxes under your name</b>");

	}			

        function showPage()
        {
                $this->t_body->pparse("finaloutput", "tplBODY");
        }
}

$myPage = new ListSubmissionPage();

if(isset($_GET['delete']) && $_GET['delete'])
{
	require_once("subbox.php");
	$subbox = new subBox;
	$subbox->deleteSubmissionBox($_GET['delete']);
	localRedir("/submission/listSubmission.php");
}
if(isset($_GET['undelete']) && $_GET['undelete'])
{
	require_once("subbox.php");
	$subbox = new subBox;
	$subbox->undeleteSubmissionBox($_GET['undelete']);
	localRedir("/submission/listSubmission.php");
}
else
{	
	$myPage->setSubmissions();
	$myPage->showPage();
}
?>
