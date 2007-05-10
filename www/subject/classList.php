<?php
/************************************************************
*                                                           *
*         Copyright (c) 2002-2003 Lektec Consulting         *
*                                                           *
************************************************************/
//ClassList object..displays class list with most recent visit 

require_once("../include/session.php");
require_once("../include/include.php");
require_once("../include/permission.php");
require_once("../include/syn_classlist.php");

class ClassList
{

        function ClassList()
        {
        }
  
        function displayClass($subCode)
        {
                //output week to template
			$this->t_body = new Template(TPL_directory);
			$this->t_body->set_file("tplBODY", "subjects/classList.htm");
			$this->t_body->set_block("tplBODY", "student_list", "student_list_block");
			$this->t_body->set_var("TITLE", HEADING." - Class Lists");
			include_once("../include/page.php");
	
					//setup database
			$db = @mssql_connect(MSSQL_host,MSSQL_user,MSSQL_passwd);
			$mySQLdb = new dbManager();
			$mySQLdb->dbConnect();	
	
			$query = "SELECT SubjectClasses.Description 
				FROM SynergyOne.dbo.SubjectClasses SubjectClasses
				WHERE SubjectClasses.ClassCode = '$subCode'";
			
			$result = mssql_query($query, $db);
			$name = @mssql_fetch_array($result);
			$this->t_body->set_var("SUBJECTNAME", $name[0]);	
	
			$result = getStudents($subCode, $db);
	
		
			if(mssql_num_rows($result) > 0)
			{
				while($student = mssql_fetch_array($result))
				{
					$this->t_body->set_var("SURNAME", $student['lastname']);
					$this->t_body->set_var("FIRSTNAME", $student['firstname']);
					$this->t_body->set_var("YEARLEVEL", $student['yearlevel']);
					$this->t_body->set_var("HOUSE", $student['house']);
					
					//get each students email address
					$mysqlquery = "SELECT id as uid, username, local_email, alt_email FROM user WHERE synergy_id = '".$student['synergy_id']."'";
					$mySQLdb->dbDoQuery($mysqlquery);
					if($mySQLdb->dbCountRows() > 0)
					{	
						$email = $mySQLdb->dbGetNextArray();
		
						$emailaddr = formatEmail($email);
	
						if($emailaddr){				
							$emaillink = "<a href=\"".$_SESSION['mailhandle'].$emailaddr."\">Email</a>";
							$this->t_body->set_var("EMAIL", $emaillink);
							$classEmail .= $emailaddr.", ";
						}
						else
							$this->t_body->set_var("EMAIL", "");
			
						$this->t_body->set_var("EMAIL_ADDRESS", $emailaddr);
						//get the time since the last visit to this homepage
						$visitquery = "SELECT DATE_FORMAT(`datetime`, '%a %D %b %H:%i') as visit FROM homepage_log, category
								  WHERE category.code = '" . $subCode . "'
								  AND	category.id = homepage_log.cid
								  AND   homepage_log.user = '{$email['id']}'
						"; 
						$mySQLdb->dbDoQuery($visitquery);
						if($mySQLdb->dbCountRows() > 0)
						{
							$lastVisit = $mySQLdb->dbGetNextArray();
							$this->t_body->set_var("LASTVISIT", $lastVisit['visit']);
						}
						else
							$this->t_body->set_var("LASTVISIT", "Never");
					
					}
					else
						$this->t_body->set_var("EMAIL", "");
		
						  
					$this->t_body->parse("student_list_block", "student_list", true);		
				}
			}
			else
				$this->t_body->set_var('student_list_block', '');
			
			$this->t_body->set_var("CLASSEMAIL", $classEmail);
					$this->t_body->pparse("finaloutput", "tplBODY");
			}

}


if(isStaff($_SESSION['gid']))
{
	$page = new ClassList;
	$subject = urldecode($_GET['subject']);
	$page->displayClass($subject);
}
else
{
	error(19);
}        


?>
