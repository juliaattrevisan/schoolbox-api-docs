<?
/************************************************************
*                                                           *
*         Copyright (c) 2003-2004 Lektec Consulting         *
*                                                           *
*	Collection of functions used during login				*
*   $Id: auth.php,v 1.16 2004/04/27 05:16:48 jleckie Exp $						    
*                                                           *
************************************************************/
include('permission.php');
include('include.php');
include('syn_userinfo.php');

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

function authenticateLDAP($username, $password) {

	$ds=@ldap_connect(LDAP_host);
	@ldap_bind($ds, LDAP_user, LDAP_pass);
	$result = @ldap_search($ds, LDAP_base,'uid='.$username,array('dn'));
	$entries = @ldap_get_entries($ds, $result);
	$dn = $entries[0]['dn'];
	if($dn)
		return @ldap_bind($ds, $dn, $password);
	else
		return false;

}

function authenticateSQL(&$db, $username, $password) {
	// Function to check username exists and password is correct

	$query = "SELECT user.id FROM user WHERE username = '". trim( $username)."' 
	AND password = PASSWORD('".$password."')";
	$db->dbDoQuery($query);
	$result = $db->dbCountRows();
	//$user = $db->dbGetNextArray();
	if($result == 1)
		return true;
	else
		return false;
}

function authenticate(&$db, $username, $password, $remember = 0)
{

	$result = false;
	
	// Function to check username exists and password is correct
	if(LDAP_enable) {
		$result = authenticateLDAP($username, $password);
	}
	
	if(!$result) {
		//fall back on SQL if ldap is not enable or fails
		$result = authenticateSQL(&$db, $username, $password);
	}
	if($result == true) {

		$query = "SELECT user.id, title, firstname, lastname, username, group_id, synergy_id, superusers.id AS su, password, email_pass, local_email, mail_handler 
		FROM user LEFT JOIN superusers ON user.id = superusers.id WHERE username = '". trim( $username)."'";
		$user = $db->dbGetSingleRow($query);
	}
	
	//log the authentication attempt
	$ip = getIP(); 
	$loginlogquery = "INSERT INTO login_log (username, date, ip, login) 
			VALUES ('$username', NOW(), '$ip'";

	//check password
	if( $result == true && is_array($user))
	{
		/*if(REGISTER_FIRSTIME)
		{
			if(!isset($user['password']) || $user['password'] == "")
			{
				//firstime user lets register there details
				if(!firsttime(&$db, $username, $password))
					return false;		
			}
		}*/

		//register users id
		$_SESSION['title']	= $user['title'];
		$_SESSION['firstname'] = $user['firstname'];
		$_SESSION['lastname'] = $user['lastname'];
		$_SESSION['id'] = $user['id'];
		$_SESSION['sid'] = $user['synergy_id'];
		$_SESSION['gid'] = $user['group_id'];
		$_SESSION['local_email'] = $user['local_email'];
		$_SESSION['lasttime'] = mktime();
		$_SESSION['username'] = $user['username'];

		//remember users session (i.e. never let it expire)
		if(REMEMBERSESSION && $remember)
			$_SESSION['remember'] = 1;
		else
			$_SESSION['remember'] = 0;			
		
		//if no webmail enabled or user has elected to use external client set mailhandle to mailto:
		if(MAIL_enable == 0 || $user['mail_handler'])
			$_SESSION['mailhandle'] = "mailto:";
		else
			$_SESSION['mailhandle'] = "/mail/createMail.php?replyto=";

		//if wenona store password in session for single signon
		//if(SITE_location == 'wenona')
		//{
			$_SESSION['password'] = $user['email_pass'];
		//}
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

		if(SYNERGY_enable)
			setSynergyInfo(&$db);
		else {
			//testing parent system
			if($_SESSION['id'] == 6277)
				$_SESSION['children'][] = array('id'=>2673,'sid'=>48826);
		}

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
	
	return false;
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
	$semester = getCurrentSemester();
	$_SESSION[ 'FileSemester'] = $semester[ 'FileSemester'];
	$_SESSION[ 'FileYear'] = $semester[ 'FileYear'];
	$synergy_id = $_SESSION['sid'];
	
	if(is_numeric($synergy_id)) {

		//if user is parent get information regarding enrolled students
		if( isParent($_SESSION['gid']))
		{
			$studentGroup = unserialize(STUDENTGROUPIDS);
			$children = getEnrolledStudents($synergy_id);
			foreach($children as $child)
			{
				$myquery = "SELECT id FROM user WHERE synergy_id = $child";
				$portal = $mydb->dbGetSingleRow($myquery);
				if($portal['id'])
				{
					$subjects = getSubjectList($child, $studentGroup[0]);
					$_SESSION['children'][] = array('id'=>$portal['id'], 'sid'=>$child, 'subjects'=>$subjects);
				}
			}
		}
		else  //user is not parent so get timetable information
		{
			$subjectList = getSubjectList($synergy_id, $_SESSION['gid']);
			if( is_array($subjectList))
			{
				foreach($subjectList as $subject)
				{
					//check subject exists.. we cant link to subjects that dont exist its ugly
					$myquery = "SELECT id FROM category WHERE code = '".$subject['code']."'";
					$mydb->dbDoQuery($myquery);
					if($mydb->dbCountRows() > 0)
						$_SESSION['subjects'][] = $subject;
				}
			}
	
			if(is_array($_SESSION['subjects']))
				$_SESSION['timetable'] = 1;
			else	
				$_SESSION['timetable']=0;
		}
	}
	
}

?>
