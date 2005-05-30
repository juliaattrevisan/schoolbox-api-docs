<?
/************************************************************
*                                                           *
*         Copyright (c) 2003-2004 Lektec Consulting         *
*                                                           *
*	Collection of functions used during login				*
*   $Id: auth.php,v 1.16 2004/04/27 05:16:48 jleckie Exp $						    
*                                                           *
************************************************************/
include("permission.php");
include("include.php");

function checkBruteForce(&$db)
{
        // Function to check that host isnt brute forceing the login
        // Returns true if too many logins false if ok
	$ip = getenv('REMOTE_ADDR');
	$graceperiod = time() - LOGINGRACE;
        
	$query = "SELECT id FROM login_log WHERE ip = '$ip' AND login = '0' AND date > FROM_UNIXTIME($graceperiod)";
	$db->dbDoQuery($query);
	
	if($db->dbCountRows() < LOGINALLOWED)
		return 0;
	else
		return 1;
} 

function authenticate(&$db, $username, $password, $remember = 0)
{
	// Function to check username exists and password is correct

	$query = "SELECT user.id, username, group_id, synergy_id, superusers.id AS su, password, email_pass, local_email, mail_handler 
	FROM user LEFT JOIN superusers ON user.id = superusers.id WHERE username = '". trim( $username)."' 
	AND ((password = PASSWORD('".$password."')) 
	OR (password IS NULL) OR (password = ''))";
	$db->dbDoQuery($query);

	$result = $db->dbCountRows();
	$user = $db->dbGetNextArray();

	//log the authentication attempt
	$now = time();
	$ip = getenv('REMOTE_ADDR');
	$loginlogquery = "INSERT INTO login_log (username, date, ip, login) 
			VALUES ('$username', FROM_UNIXTIME($now), '$ip'";

	//check password
	if( $result == 1)
	{
		if(REGISTER_FIRSTIME)
		{
			if(!isset($user['password']) || $user['password'] == "")
			{
				//firstime user lets register there details
				if(!firsttime(&$db, $username, $password))
					return false;		
			}
		}

		//register users id
		$_SESSION['id'] = $user['id'];
		$_SESSION['sid'] = $user['synergy_id'];
		$_SESSION['gid'] = $user['group_id'];
		$_SESSION['local_email'] = $user['local_email'];
		$_SESSION['lasttime'] = mktime();

		//remember users session (i.e. never let it expire)
		if(REMEMBERSESSION && $remember)
			$_SESSION['remember'] = 1;
		else
			$_SESSION['remember'] = 0;			
		
		//if no webmail enabled or user has elected to use external client set mailhandle to mailto:
		if(MAIL_enable == 0 || $user['mail_handler'])
			$_SESSION['mailhandle'] = "mailto:";
		else
			$_SESSION['mailhandle'] = "/mail/send.php?replyto=";

		//if wenona store password in session for single signon
		if(SITE_location == 'wenona')
		{
			$_SESSION['username'] = $user['username'];
			$_SESSION['password'] = $user['email_pass'];
		}
		//superuser value, used to bypass most security checks
		if($user['su'] != "")
			$_SESSION['su'] = 1;

		//register there ip
		$_SESSION['ip'] = getIP(); 

		//campus
		$campusquery = "SELECT campus 
				FROM userCampus 
				WHERE uid = '".$user['id']."'
				ORDER BY defaultCampus DESC
				";
		$db->dbDoQuery($campusquery);
		while($campus = $db->dbGetNextArray() )
		{
			if($campus['campus'])
				$_SESSION['campus'][] = $campus['campus'];
		}

		//$SESSION['time'] = time();
		
		if(SYNERGY_enable && $_SESSION['sid'])
			setSynergyInfo(&$db);

		$loginlogquery .= ", '1')";
		$db->dbDoQuery($loginlogquery);

		return true;
	}
	else
	{
		$loginlogquery .= ", '0')";
		$db->dbDoQuery($loginlogquery);
		return false;		
	}
}	

function firsttime(&$db, $username, $password)
{
	//registers users for the firstime

        //validate username and password with email server

        $mailserver = MAIL_host;
        $server="{".$mailserver.":110/pop3}INBOX";
        $inbox = @imap_open ($server, $username, $password);
        if(!$inbox)
        {
      		//failed to check password against mailserver 
	  	return 0;
	}

        //submit email password to database
        $query = "UPDATE user SET password = PASSWORD('".$password."'), email_pass = '".$password."' 
		WHERE username = '".$username."'";
        $db->dbDoQuery($query);

	return 1;
}

function setSynergyInfo(&$mydb)
{
	//setup a users enrolement for the session

	$db = mssql_connect(MSSQL_host,MSSQL_user,MSSQL_passwd);
	mssql_select_db( MSSQL_db);

	$year = date("Y");
	$synergy_id = $_SESSION['sid'];

	#Get current FileSemester and FileYear
        $sql_fs = "SELECT FileSemester, FileYear
                   FROM FileSemesters
                   WHERE (SystemCurrentFlag=1)
                   ";
        $query_fs = mssql_query( $sql_fs)
            or die( "Couldn't get FileSemester and FileYear- " . mssql_get_last_message());
        $row_fs = mssql_fetch_array( $query_fs);

        //get synergy info
        $_SESSION[ 'FileSemester'] = $row_fs[ 'FileSemester'];
        $_SESSION[ 'FileYear'] = $row_fs[ 'FileYear'];


	if( isStudent($_SESSION['gid']) )
	{
		$sql ="SELECT DISTINCT(SubjectClasses.ClassCode), SubjectClasses.Description
			FROM SynergyOne.dbo.SubjectClasses SubjectClasses,
			SynergyOne.dbo.vStudentTimetableFull vStudentTimetableFull
			WHERE SubjectClasses.ClassCode = vStudentTimetableFull.ClassCode AND
			SubjectClasses.FileSemester = vStudentTimetableFull.FileSemester AND
			SubjectClasses.FileType = vStudentTimetableFull.FileType AND
			SubjectClasses.FileYear = vStudentTimetableFull.FileYear AND
			((vStudentTimetableFull.FileSemester=".$_SESSION['FileSemester'].") AND
			(vStudentTimetableFull.FileYear='$year') AND
			(vStudentTimetableFull.StudentID='$synergy_id'))";
	}
	else
	{      

		$sql = '
			select	distinct(c1.ClassCode),
				c2.description
			from	SubjectClassStaff c1,
				SubjectClasses c2
			where	c1.ClassCode = c2.ClassCode 
				AND
				c1.FileSemester = ' . $_SESSION['FileSemester'] . '
				AND
				c1.FileYear = ' . $year . '
				AND
				c1.StaffID = ' . $synergy_id . '
				AND
				c2.Description <= all ( select description from subjectclasses c3 where c3.classcode=c1.classcode)';
	}
	
	$result = mssql_query( $sql, $db)
		or die( "Couldn't query subjects - " . mssql_get_last_message());
	if($result != 1)
	{
	if( mssql_num_rows( $result ) > 0 )
	{
		$x=0;
		$_SESSION['timetable']=1;
		while($subject = @mssql_fetch_array($result))
		{
			//check subject exists..
			$myquery = "SELECT id FROM category WHERE code = '".$subject[0]."'";
			$mydb->dbDoQuery($myquery);
			if($mydb->dbCountRows() > 0)
			{
				//remove study periods and rostered offs
        			if(!preg_match("/ST|RO/",$subject[0]))
        			{
					preg_match("/([a-zA-Z \.0-9]+)/",$subject[1],$matches);
					$_SESSION['subjects'][$x] = $matches[1];
					$_SESSION['sub_code'][$x] = $subject[0];
					$x++;
				}
			}
		}
	}
	}
	else {
		$_SESSION['timetable']=0;
	}
	
}
?>
