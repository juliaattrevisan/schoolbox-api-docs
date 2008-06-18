<?php
/************************************************************
*                                                           *
*         Copyright (c) 2002-2003 Lektec Consulting         *
*                                                           *
************************************************************/
//ClassList object..displays class list with most recent visit 

require_once("../include/permission.php");
require_once("../include/syn_classlist.php");


if(isStaff($_SESSION['gid']))
{
	$page = new ClassList;
	$subject = urldecode($_GET['subject']);
	
	$this->setFile(TEMPLATE_content, 'class/classList.htm');
	$this->setBlock(TEMPLATE_content, 'student_list', 'student_list_block');
	$this->setVar("TITLE", HEADING." - Class Lists");

	//setup database
	$db = @mssql_connect(MSSQL_host,MSSQL_user,MSSQL_passwd);
	$mySQLdb = new dbManager();

	$query = "SELECT name FROM category WHERE code = '$subCode'";
	$category = $mySQLdb->getRow($query);

	$this->setVar("SUBJECTNAME", $category['name'] ." ($subCode)");	

	$result = getStudents($subCode, $db);

	if(mssql_num_rows($result) > 0)
	{
		while($student = mssql_fetch_array($result))
		{
			$email = '';
			$emailaddr = '';
			
			//get each students email address
			$mysqlquery = "SELECT id as uid, username, local_email, alt_email FROM user WHERE synergy_id = '".$student['synergy_id']."'";
			$email = $mySQLdb->getRow($mysqlquery);
			
			if(is_array($email)){
				$emailaddr = formatEmail($email);
				if($emailaddr){				
					$emaillink = "<a href=\"".$_SESSION['mailhandle'].$emailaddr."\">Email</a>";
					$classEmail .= $emailaddr.", ";
				}
	
				//get the time since the last visit to this homepage
				$visitquery = "SELECT DATE_FORMAT(`datetime`, '%a %D %b %H:%i') as visit FROM homepage_log, category
						  WHERE category.code = '" . $subCode . "'
						  AND	category.id = homepage_log.cid
						  AND   homepage_log.user = '{$email['uid']}'
				"; 
				$lastVisit = $mySQLdb->getRow($visitquery);
			
			}
		
		
			$studentOutput = array(	'SURNAME' 	=> $student['lastname'],
									'FIRSTNAME' => $student['firstname'],
									'YEARLEVEL' => $student['yearlevel'],
									'HOUSE' 	=> $student['house'],
									'LASTVISIT' => is_array($lastVisit)?$lastVisit['visit']:'Never',
									'ID'		=> $email['uid'],
									'SYNERGYID'	=> $student['synergy_id'],
									'EMAIL'		=> $emailaddr?$emaillink:'',
									'EMAIL_ADDRESS'=> $emailaddr,
									);
									
			$this->setVar($studentOutput);
				  
			$this->t_body->parse("student_list_block", "student_list", true);		
		}
	}
	else 
		$this->t_body->set_var('student_list_block', '');
	
	
	$this->setVar("REPLYTO", formatEmail($_SESSION));
	$this->setVar("CLASSEMAIL", $classEmail);
	
}
else
{
	error(19);
}    

			


    


?>
