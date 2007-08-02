<?PHP
//connect to database
require_once( '../include/common.php');

$link = mysql_connect( DB_HOST, DB_USER, DB_PASSWD);
mysql_select_db( DB_NAME, $link);

// Pre Looked up IDs!!! ha sucked in, i did it anyways!

$constid=array(
'0'=>'1',
'44109'=>'4',
'14447'=>'9',
'15800'=>'10',
'47205'=>'36',
'27838'=>'786',
'46543'=>'962',
'45494'=>'2008',
'6682'=>'2040',
'24884'=>'2044',
'37736'=>'2058',
'37747'=>'2059',
'35260'=>'2094',
'6512'=>'2195',
'51425'=>'2341',
'51347'=>'2483',
'51346'=>'2486',
'37456'=>'2725',
'54398'=>'2772',
'54266'=>'2774',
'54378'=>'2776',
'27038'=>'3895',
'26872'=>'3896',
'28618'=>'3897',
'55129'=>'3929'
);
		
//select all equipment
$countConsts = 0;
$countPLooks = 0;
$countPerrsB = 0;
$countPerrsS = 0;
$countPLooksS = 0;
$countPerrsBS = 0;
$countPerrsSS = 0;
$fuckups = 0;


$sqlE = "SELECT equipmentID, userID, userNonSynergy from ".DB_NAME.".tblEquipment";
$queryE = mysql_query($sqlE);

while ($rowE = mysql_fetch_array($queryE))
	{
	$userID = trim($rowE['userID']);
	$userNonSynergy = trim ($rowE['userNonSynergy']);
	if ( $userNonSynergy == '' && $userID == '')
		{
		$newPID = '';
		}
	else if(isset($constid[$userID]))
		{
		$newPID = $constid[$userID];
		$countConsts ++;		
		}
	else if(isset($constid[$userNonSynergy]))
		{
		$newPID = $constid[$userNonSynergy];
		$countConsts ++;		
		}		
	else // do db lookups
		{
		$sqlP = "SELECT id from ".DB_NAME_PORTAL.".user where synergy_id = '".$userID."'";
		$queryP = mysql_query($sqlP);
	
		if(mysql_num_rows($queryP) >= 1)
			{
			$rowP = mysql_fetch_array($queryP);
			$countPLooks ++;
			$newPID = $rowP['id'];
			}
		else
			{
			$countPerrsS ++;
			$newPID = 1;
			}

		if ( $userNonSynergy == '' && $userID == '')
			{
			$newPID = '';
			}	
		}
			
	$sqlUpdate = "update ".DB_NAME.".tblEquipment SET portalUserID = '".$newPID."' 
					where equipmentID = '".$rowE['equipmentID']."'";
					
	mysql_query($sqlUpdate);
	print $sqlUpdate."<br>";	
				
	}//end while

print "<br>Look ups from array = ".$countConsts;
print "<br>Correct Look ups from portal = ".$countPLooks;
print "<br>Portal look ups that returned multiple rows and not in array = ".$countPerrsB ;
print "<br> portal look ups that returned no rows = ".$countPerrsS;
	






		//else if( != '')
		//	{
		//	$sqlPS = "SELECT id from intranet.user where synergy_id = '".."'";
		//	$queryPS = mysql_query($sqlPS);
		/*
			if(mysql_num_rows($queryPS) == 1)
				{
				$rowPS = mysql_fetch_array($queryPS);
				$countPLooksS ++;
				$newPID = $rowPS['id'];
				}
				
			else if(mysql_num_rows($queryPS) > 1)
				{
				$countPerrsBS ++;
				$newPID = "1";
				}
			else
				{
				$countPerrsSS ++;
				$newPID = 1;
				}
			$sqlUpdate = "update tblEquipment SET portalUserID = '".$newPID."' where userID = '".$userNonSynergy."'";
			print $sqlUpdate."<br>";
			mysql_query($sqlUpdate);	
			}
		*/

?>


