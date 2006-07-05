<?php
/************************************************************
*                                                           *
*         Copyright (c) 2002-2003 Lektec Consulting         *
*                                                           *
************************************************************/
//editable class mark sheet

require_once("../include/session.php");
require_once("../include/include.php");
require_once("../include/involved.php");
require_once("../include/permission.php");
require_once("../include/syn_classlist.php");


if(isset($_GET['subject']) && $_GET['subject'])
{
	$subject = urldecode($_GET['subject']);

	if(isStaff($_SESSION['gid']) && involvedInSubject($subject) || $_SESSION['su']) {
		if( $_POST[ 'do'] == 'markUpdate') {
			if( $_POST[ 'mark'] == '') {
				markDelete( $_POST[ 'userID'], $_POST[ 'box_id']);
			}
			else {
				markUpdate( $_POST[ 'userID'], $_POST[ 'box_id'], $_POST[ 'mark']);
			}
		}
		elseif(( $_POST[ 'do'] == 'markAdd') and ( $_POST[ 'mark'] != '')) {
			markAdd( $_POST[ 'userID'], $_POST[ 'box_id'], $_POST[ 'mark']);
		}
		elseif( $_POST[ 'do'] == 'columnAdd') {
			columnAdd( $_POST[ 'cid'], $_POST[ 'hiddenColName'], $_POST[ 'studentID']);
		}
		elseif( $_POST[ 'do'] == 'columnDelete') {
			columnDelete( $_POST[ 'box_id']);
		}

		if($_POST['ajaxPost'] == true)
		{
			echo"Query Executed OK";
		}
		else
		{
			$page = new ClassList;
			$page->displayClass($subject);
		}
	}
	else
	{
		error(19); //do not have permission
	}
}
else
{
	error(2); //not enough info
}



class ClassList
{
	var $_cid;		# category ID for this subject

        function displayClass($subCode)
        {
                //output week to template

                $this->t_body = new Template(TPL_directory);
                $this->t_body->set_file("tplBODY", "submission/classMarkList.htm");
		$this->t_body->set_block("tplBODY", "student_list", "student_list_block");
		$this->t_body->set_var("TITLE", HEADING." - Mark Lists");
		include_once("../include/page.php");

                //setup database
		$db = @mssql_connect(MSSQL_host,MSSQL_user,MSSQL_passwd);
		$mySQLdb = new dbManager();
		$mySQLdb->dbConnect();	

		$query = "SELECT id, name FROM category WHERE code = '$subCode'";
		$mySQLdb->dbDoQuery($query);
		$category = $mySQLdb->dbGetNextArray();
		$this->_cid = $category[ 'id'];
	
		$this->t_body->set_var("SUBJECTNAME", $category['name']);
		$this->t_body->set_var("CODE", $subCode);	

		$result = getStudents($subCode);	

		//keep count of the total marks 
		$total = 0;
		$userCount = 0;
		
		while($student = @mssql_fetch_array($result))
 		{
			$users[$userCount]['surname'] = $student['lastname'];	
			$users[$userCount]['firstname'] = $student['firstname'];	
			$users[$userCount]['synergy_id'] = $student['synergy_id'];	

			//get each students marks
			$mysqlquery = "
				SELECT	r.mark,
					b.description,
					b.id,
					u.id as userID
				FROM	submission_return r,
					submission_box b,
					user u
				WHERE	u.id = r.owner
					AND
					r.box_id = b.id	
					AND 
					b.category = '".$category['id']."' 
					AND
					u.synergy_id = '".$student['synergy_id']."'
				ORDER BY b.date";
			
			$mySQLdb->dbDoQuery($mysqlquery);
			if($mySQLdb->dbCountRows() > 0)
			{	
				$count = 0;

				//for each assignment record there mark
				while( $work = $mySQLdb->dbGetNextArray())
				{
					$users[$userCount]['work'][$work['id']] = $work['mark'];	
					$users[$userCount]['userID'] = $work['userID'];	
				}
			}
			else
			{
				//ok if we dont get the users id because the user hasnt had work marked yet
				//lets get it now just for the purpose of editing there mark on the sheet
				$mysqlquery = "
					SELECT 	id as userID
					FROM 	user 
					WHERE 	synergy_id = '".$student['synergy_id']."'
				";
		
				$mySQLdb->dbDoQuery($mysqlquery);
				$work = $mySQLdb->dbGetNextArray();
				$users[$userCount]['userID'] = $work['userID']; 



			}
		
			$userCount++;
			
			if(!$studid && $work['userID'] )
				$studid = $work['userID'];


		}

		//ok now we need to get the list of work
		
		$query = "SELECT description, id, deleted FROM submission_box WHERE category = '".$category['id']."'";
		$mySQLdb->dbDoQuery($query);
	
		$total = $mySQLdb->dbCountRows();
		if($total > 0 )
		{
			//output header
			$workList = array();

			$worknameHTML = "";
			while( $box = $mySQLdb->dbGetNextArray() )
			{	
				if($box['deleted'])
					$name = str_replace( ' ', '&nbsp;', $box['description']);
				else
					$name = "<a href='viewSubmission.php?box_id=".$box['id']."'>
					" . str_replace( ' ', '&nbsp;', $box['description']) . "</a>";

				$worknameHTML .= "
					<th>
					<div style='bottom: 0px; writing-mode: tb-rl; filter: flipH() flipV();'>
					<form method='POST'>$name<input type='hidden' name='box_id' value='" . $box[ 'id'] . "'>
					<input type='hidden' name='do' value='columnDelete'><input type='image' src='/images/button_cross.jpg' width='10' align='right' onClick='return confirm( \"Delete this column?\")'>
					</form>
					</div>
					</th>
				";
			
				array_push($workList, $box);

			}
			$this->t_body->set_var("WORKNAMES", $worknameHTML);
		}
		else {
			$this->t_body->set_var("WORKNAMES", "");
		}

		$this->t_body->set_var( 'EXAMPLE_STUDENTID', $studid);
		$this->t_body->set_var( 'CID', $this->_cid);
		
		//output user and their marks
		if(is_array($users))
		{
		foreach($users as $user)
		{
			//output a user
			$this->t_body->set_var("SURNAME", $user['surname']);
			$this->t_body->set_var("FIRSTNAME", $user['firstname']);

			if($total > 0) //no work to output
			{
				$workmarkHTML = "";			# Clear marks for new row
				foreach($workList as $box)
				{
									# No mark given yet
					if($user['work'][$box['id']] == "") {
						$workmarkHTML .= '
						
						 <td><form method="POST" onSubmit="return false;">
							<input name="mark" value="" class="inputMark" type="text" size="5" maxlength="10" onBlur="submitOnFieldChange(this.form);">
							<div style="display: none;">
							<input type="hidden" name="userID" value="' . $user[ 'userID'] . '">
							<input type="hidden" name="box_id" value="' . $box[ 'id'] . '">
							<input type="hidden" name="do_" value="markAdd">
							</div></form>
						</td>';
					}
					else {				# Mark has been given
						$workmarkHTML .= '
						
						  <td><form method="POST" onSubmit="return false;">
							<input name="mark" class="inputMark" type="text" size="5" maxlength="10" value="' . htmlentities( $user[ 'work'][ $box[ 'id']]) . '" onBlur="submitOnFieldChange(this.form);">
							<input type="hidden" name="userID" value="' . $user[ 'userID'] . '">
							<input type="hidden" name="box_id" value="' . $box[ 'id'] . '">
							<input type="hidden" name="do_" value="markUpdate">
						  </form></td>
						';
					}
				}
				$this->t_body->set_var("WORKMARKS", $workmarkHTML);
			}				
			else
				$this->t_body->set_var("WORKMARKS", "");

			$this->t_body->parse("student_list_block", "student_list", true);		
		}
		}
		else
			$this->t_body->set_var("student_list_block", "");
		
	$this->t_body->pparse("finaloutput", "tplBODY");
	}

}



	# Update a mark
function markUpdate( $userID, $box_id, $mark){
	$sql = "
		update	submission_return
		set	mark = '" . mysql_escape_string( $mark) . "'
		where	box_id = '" . mysql_escape_string( $box_id) . "' and owner = '" . mysql_escape_string( $userID) . "'
	";
	$mySQLdb = new dbManager();
	$mySQLdb->dbConnect();
	$mySQLdb->dbDoQuery( $sql);
	$mySQLdb->dbClose();
}

	# Add a mark
function markAdd( $userID, $box_id, $mark) {
	$sql = "insert
			into submission_return
			(box_id, owner,	mark, deleted)
		values	('" . mysql_escape_string( $box_id) . "','" .mysql_escape_string( $userID) . "','" .mysql_escape_string( $mark)."','0')";
	
	$mySQLdb = new dbManager();
	$mySQLdb->dbConnect();
	$mySQLdb->dbDoQuery( $sql);
	$mySQLdb->dbClose();
}


	# Delete a mark
function markDelete( $userID, $box_id) {
	$sql = "
		delete 
		from 	submission_return
		where	box_id = '" . mysql_escape_string( $box_id) . "'
			and
			owner = '" . mysql_escape_string( $userID) . "'
	";
	$mySQLdb = new dbManager();
	$mySQLdb->dbDoQuery( $sql);
}




	# Add a column to the mark sheet (empty submission box) for this subject
	# The reason this has to be so hacky is because of the whole hacky app design
function columnAdd( $cid, $description, $studentID) 
{
	mysql_connect( SQL_host, SQL_user, SQL_passwd);
	mysql_select_db( SQL_db);

	mysql_query( $sql);

	$ownerID = $_SESSION['id'];

	# Create dummy submission box
	$sql= "	insert
		into	submission_box
			(category, owner, description, created, deleted)
		values	('$cid', '". $ownerID ."', '".$description."', NOW(), '1')
	";

	mysql_query( $sql);

	mysql_close();
}



function columnDelete( $box_id)
{
	mysql_connect( SQL_host, SQL_user, SQL_passwd);
	mysql_select_db( SQL_db);

	# delete submission_returns
	$sql= "DELETE FROM submission_return WHERE box_id = '$box_id'";

	mysql_query( $sql)
		or die( "Error deleting column - couldn't delete submission_return for box_id $box_id<br>sql was $sql");


		# delete submission box
	$sql= "	DELETE FROM submission_box WHERE id = '$box_id'";

	mysql_query( $sql)
		or die( "Error deleting column - couldn't delete submission box $box_id<br>sql was $sql");

	mysql_close();
}

?>
