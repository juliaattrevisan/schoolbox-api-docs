<?php

require_once("../include/session.php");
require_once("../include/include.php");

class returnedWorkPage 
{
	function returnedWorkPage()
	{
		$this->t_body = new Template(TPL_directory);
		$this->t_body->set_file("tplBODY", "submission/returnedWork.htm");
		$this->t_body->set_var("TITLE", HEADING." - Returned Work");
		$this->t_body->set_block("tplBODY", "return_block", "return_list"); 
		$this->t_body->set_block("tplBODY", "category_block", "category_listing");
				
		include_once("../include/page.php");

		$this->db = new dbManager;
		$this->db->dbConnect();

	}

	function getReturnedWork()
	{

		//log page
		$this->logPageView();

		$query = "SELECT category.id as cid, category.name, file.description, submission_return.mark, 
				submission_return.comment, 
				submission_return.id, submission_return.filename 
				FROM submission_return, category, file, submission_box
				WHERE submission_return.box_id = submission_box.id AND submission_box.category = category.id 
				AND submission_box.fid = file.id AND submission_return.deleted = '0' 
				AND submission_return.owner = '".$_SESSION['id']."'";

		$this->db->dbDoQuery($query);

		if($this->db->dbCountRows() > 0)
		{
			$return = $this->db->dbGetNextArray();
			$prevCat = $return;			
			do
			{

				if($prevCat['cid'] != $return['cid'])
				{
					$this->t_body->set_var("CATEGORY", $prevCat['name']);
 					$this->t_body->parse("category_listing", "category_block", true);
					$this->t_body->set_var("return_list", "");
				}


				$this->t_body->set_var("ASSDESC", $return['description']);
				$this->t_body->set_var("MARK", $return['mark']);
				$this->t_body->set_var("COMMENT", $return['comment']);
				
				$this->t_body->set_var("DELETE", "returnedWork.php?delete=".$return['id']);
				if($return['filename'] == "")
					$this->t_body->set_var("VIEW", "");
				else
					$this->t_body->set_var("VIEW", 
						"<a href=\"sendret.php?view=".$return['id']."\" target=\"_blank\">View</a> /");
						
				$this->t_body->parse("return_list","return_block",true);
				
				$prevCat = $return;
			}while($return = $this->db->dbGetNextArray());


		$this->t_body->set_var("CATEGORY", $prevCat['name']);
		$this->t_body->parse("category_listing", "category_block", true);

		}
		else
			$this->t_body->set_var("category_listing", "<b>You currently have no returned work</b>");
		
	}		

        function logPageView()
        {                       
                $testquery = "SELECT datetime FROM submission_log WHERE page = '2'
                AND user = '".$_SESSION['id']."'";
                                        
                $this->db->dbDoQuery($testquery);
                        
                if($row = $this->db->dbGetNextArray() )
                {
                        $query = "UPDATE submission_log SET datetime = NOW() WHERE page =
                        '2' AND user = '".$_SESSION['id']."'";
                }
                else
                {
                        $query = "INSERT INTO submission_log (datetime, user, page) VALUES ( NOW() ,
                                '".$_SESSION['id']."', '2')";
                }
                                        
                $this->db->dbDoQuery($query);
        } 
	
	function deleteReturnedWork($id)
	{
		//mark work deleted but only when the work is owned by user

		$query = "UPDATE submission_return SET deleted = '1' WHERE id = '$id' AND owner = '".$_SESSION['id']."'";
		$this->db->dbDoQuery($query);
	}

	function viewReturnedWork($id, $save)
	{
                //sends students work to student only
		$query = "SELECT filename FROM submission_return WHERE owner = '".$_SESSION['id']."' AND id = '$id'";
		$this->db->dbDoQuery($query);
		
		if($this->db->dbCountRows() < 1)
                {
			error(5);
                }	

		$result = $this->db->dbGetNextArray();		
                if($save)
                        $attach = "attachment;";
                else
                        $attach = "";

                //all checks completed.. continue with file send
                $filename = RETURNS_directory . "/" . $id . ".dat";

                //send headers
                header ("Content-Type: application/octet-stream"); 
                header ("Content-Disposition: $attach filename=".$result['filename'].""); 
                header ("Content-Length: ".filesize("$filename"));
                readfile($filename);

	}

	function showPage()
	{
		$this->t_body->pparse("finaloutput", "tplBODY");
	}
}



$page = new returnedWorkPage();

//if user requests to view send them the file
if(isset($_REQUEST['view']) && $_REQUEST['view'])
	$page->viewReturnedWork($_REQUEST['view'], $_REQUEST['save']);
//mark work deleted and then display
else if(isset($_REQUEST['delete']) && $_REQUEST['delete'])
{
	$page->deleteReturnedWork($_REQUEST['delete']);
	$page->getReturnedWork();
	$page->showPage();
}
//just display page
else
{
	$page->getReturnedWork();
	$page->showPage();
}
