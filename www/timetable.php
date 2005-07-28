<?php
/************************************************************
*                                                           *
*         Copyright (c) 2002-2003 Lektec Consulting         *
*                                                           *
************************************************************/
//timetable object that displays the users timetable

require_once("include/session.php");
require_once("include/include.php");
require_once("include/permission.php");

class Timetable
{

        function Timetable()
        {
                //output week to template
                $this->t_body = new Template(TPL_directory);
		$this->t_body->set_file("tplBODY", "timetable.htm");
		$this->t_body->set_var("TITLE", HEADING." - Timetable");
                //setup blocks
                $this->t_body->set_block("tplBODY", "period", "period_listing");
                $this->t_body->set_block("period", "day", "day_listing");
                $this->t_body->set_block("tplBODY", "day_header", "day_header_listing");

		include_once("include/page.php");
        }

	function getQuery()
	{	
		$synergy_id = $_SESSION['sid'];
	
 		if( isStudent($_SESSION['gid']))
		{
			$query ="SELECT Timetable.Room, SubjectClasses.Description, Timetable.DayNumber, 
				Timetable.PeriodNumber, Timetable.ClassCode 
				FROM SynergyOne.dbo.StudentClasses StudentClasses, 
				SynergyOne.dbo.SubjectClasses SubjectClasses, SynergyOne.dbo.Timetable 
				Timetable 
				WHERE Timetable.ClassCampus = SubjectClasses.ClassCampus AND 
				Timetable.ClassCode = SubjectClasses.ClassCode AND Timetable.FileSemester = 
				SubjectClasses.FileSemester AND Timetable.FileType = SubjectClasses.FileType 
				AND Timetable.FileYear = SubjectClasses.FileYear AND 
				StudentClasses.FileSemester = SubjectClasses.FileSemester AND 
				StudentClasses.FileSemester = Timetable.FileSemester AND 
				StudentClasses.FileType = SubjectClasses.FileType AND 
				StudentClasses.FileType = Timetable.FileType AND StudentClasses.FileYear = 
				SubjectClasses.FileYear AND StudentClasses.FileYear = Timetable.FileYear AND 
				StudentClasses.ClassCampus = SubjectClasses.ClassCampus AND 
				StudentClasses.ClassCode = SubjectClasses.ClassCode AND 
				((Timetable.FileSemester=".$_SESSION['FileSemester'].") AND (Timetable.FileYear=".$_SESSION['FileYear'].") AND 
				(StudentClasses.ID='$synergy_id')) AND Timetable.PeriodNumber > '0'
				ORDER BY Timetable.PeriodNumber, Timetable.DayNumber"; 
		}
		else
		{
			$query = "SELECT Timetable.Room, SubjectClasses.Description, Timetable.DayNumber, 
				Timetable.PeriodNumber, Timetable.ClassCode
				FROM SynergyOne.dbo.SubjectClasses SubjectClasses, SynergyOne.dbo.Timetable 
				Timetable 
				WHERE Timetable.ClassCampus = SubjectClasses.ClassCampus AND 
				Timetable.ClassCode = SubjectClasses.ClassCode AND Timetable.FileSemester = 
				SubjectClasses.FileSemester AND Timetable.FileType = SubjectClasses.FileType 
				AND Timetable.FileYear = SubjectClasses.FileYear AND 
				((SubjectClasses.FileSemester=".$_SESSION['FileSemester'].") AND 
				(SubjectClasses.FileYear=".$_SESSION['FileYear'].") AND 
				(Timetable.StaffID='$synergy_id')) AND Timetable.PeriodNumber > '0' 
				ORDER BY Timetable.PeriodNumber, Timetable.DayNumber";
		}
		return $query;
	}

        function displayWeek()
        {

                //setup database
		$db = mssql_connect(MSSQL_host,MSSQL_user,MSSQL_passwd);				
			
		$query = $this->getQuery();
			
		$result = mssql_query($query, $db);
	        
		include("include/syn_timetable.php");
		$periodNum = getPeriodNumber();

		$dayNum = getDayNumber();	

		$timetabledef = unserialize(TIMETABLE_definition);
		
		$p = $timetabledef[$_SESSION['campus'][0]]['period'];
		$d = $timetabledef[$_SESSION['campus'][0]]['day'];

		if ($result != 1)
			$desc = mssql_fetch_array($result);
	 	else
			$desc = "";	
	

		$flag = 1;
		
		//timetable field width
		$this->t_body->set_var("TIMETABLE_FIELD_WIDTH", round((100 / ($d+1)),1));

		//for each period
		for ($x = 1; $x <= $p; $x++) 
		{
			$this->t_body->set_var("PERIOD", $timetabledef[$_SESSION['campus'][0]]['periodnames'][$x]);
			
			
			//for each day
			for ($y = 1; $y <= $d; $y++)
			{	
			
				if($x == 1) // only output header on first run through
				{
					//output day header
					$this->t_body->set_var("DAY_NUMBER", $y);
	        			$this->t_body->parse("day_header_listing", "day_header", true);
				}

				if ($desc[3] != $x || $desc[2] != $y)
					$flag = 0;
				else 
					$flag = 1;

				$dayvar = "DAY";
				$col = "COL";

				if ($flag == 0)
				{	
					$period = "-"; 
				}
				else
				{
					if ($_SESSION['campus'][0] == 3){
						$post = strpos($desc[4], "EN");
						if (($post) == 2)
							$period = "Homeroom";
						else
							$period = $desc[1]."<br />(".$desc[4].")<br />";
					}
					else   
						$period = $desc[1]."<br />(".$desc[4].")<br />".$desc[0]."<br />";
					
					$desctest = mssql_fetch_array($result);
					
					if ($desctest[2] == $y && $desctest[3] == $x)
					{
					$desc = mssql_fetch_array($result);
					}
					else
					{
					$desc = $desctest;
					}
				}

				$this->t_body->set_var($dayvar, $period);
				
				if($y == $dayNum && $x == $periodNum)
					$this->t_body->set_var("TIMETABLE_CLASS", "current");
				else	
					$this->t_body->set_var("TIMETABLE_CLASS", "normal");

				if($y==1)
					$this->t_body->parse("day_listing", "day", false);
				
				else
					$this->t_body->parse("day_listing", "day", true);

			}
        	$this->t_body->parse("period_listing", "period", true);
			
                }
                $this->t_body->pparse("finaloutput", "tplBODY");
        }

}

$timetable = new Timetable();

$timetable->displayWeek();


?>
