<?php
/************************************************************
*                                                           *
*         Copyright (c) 2003-2004 Lektec Consulting         *
*                                                           *
*	Collection of functions used during login				*
*   $Id: auth.php,v 1.16 2004/04/27 05:16:48 jleckie Exp $						    
*                                                           *
************************************************************/
require_once('permission.php');
require_once('include.php');
require_once('syn_userinfo.php');

function checkBruteForce(&$db)
{
        // Function to check that host isnt brute forceing the login
        // Returns true if too many logins false if ok
	$ip = getenv('REMOTE_ADDR');
	$graceperiod = time() - LOGINGRACE;
        
	$query = "SELECT id FROM login_log WHERE ip = '$ip' AND login = '0' AND date > FROM_UNIXTIME($graceperiod)";
	$db->query($query);
	
	if($db->countRows() < LOGINALLOWED)
		return 0;
	else
		return 1;
} 

function authenticateLDAP($username, $password) {

        $ds=ldap_connect(LDAP_host);
        ldap_set_option($ds, LDAP_OPT_PROTOCOL_VERSION, 3);
        ldap_set_option($ds, LDAP_OPT_REFERRALS, 0);
        ldap_bind($ds, LDAP_user, LDAP_pass);
        $searchString = "(SAMAccountName=".ldap_escape($username).")";
        $result = ldap_search($ds,LDAP_base, $searchString, array('dn'));
        if($result) {
                $entries = ldap_get_entries($ds, $result);
                $dn = $entries[0]['dn'];
                if($dn)
                        return @ldap_bind($ds, $dn, ldap_escape($password));
        }
        ldap_unbind($ds);

        if(LDAP_host2)  {
			//Try student server
			$ds=ldap_connect(LDAP_host2);
			ldap_set_option($ds, LDAP_OPT_PROTOCOL_VERSION, 3);
			ldap_set_option($ds, LDAP_OPT_REFERRALS, 0);
			ldap_bind($ds, LDAP_user2, LDAP_pass);
			$result = ldap_search($ds,LDAP_base2, $searchString, array('dn'));
			if($result) {
					$entries = ldap_get_entries($ds, $result);
					$dn = $entries[0]['dn'];
					if($dn)
							return @ldap_bind($ds, $dn, ldap_escape($password));
			}
        }

        return false;

}

function ldap_escape($str, $for_dn = false)
{

    // see:
    // RFC2254
    // http://msdn.microsoft.com/en-us/library/ms675768(VS.85).aspx
    // http://www-03.ibm.com/systems/i/software/ldap/underdn.html

    if  ($for_dn)
        $metaChars = array(',','=', '+', '<','>',';', '\\', '"', '#');
    else
        $metaChars = array('*', '(', ')', '\\', chr(0));

    $quotedMetaChars = array();
    foreach ($metaChars as $key => $value) $quotedMetaChars[$key] = '\\'.str_pad(dechex(ord($value)), 2, '0');
    $str=str_replace($metaChars,$quotedMetaChars,$str); //replace them
    return ($str);
}

function authenticateSQL(&$db, $username, $password) {
	// Function to check username exists and password is correct

	$query = "SELECT user.id FROM user WHERE username = '". mysql_real_escape_string($username)."'
	AND password = PASSWORD('".mysql_real_escape_string($password)."')";
	$db->query($query);
	$result = $db->countRows();
	//$user = $db->getNextArray();
	if($result == 1)
		return true;
	else
		return false;
}

function authenticate(&$db, $username, $password, $remember = 0)
{
	$result = false;

	$username = trim($username);
	$password = trim($password);

	if (get_magic_quotes_gpc()) {
       $username = stripslashes($username);
	   $password = stripslashes($password);
   }

	// Function to check username exists and password is correct
	if(defined('LDAP_enable') && LDAP_enable) {
		$result = authenticateLDAP($username, $password);
		
		//insert password into db to enable automatic login
		//$query = "UPDATE user SET email_pass = '$password' WHERE username = '".trim($username)."'";
		//$db->query($query);
	}

    // Check kerberos authentication
    if (defined('KERB_enable') && KERB_enable ) {
         if($_SERVER['KRB5CCNAME'] && $_SERVER['REMOTE_USER']  ) {
             $username = $_SERVER['REMOTE_USER'];

             $result = true;
         }
    }


    if(!$result) {
		//fall back on SQL if ldap is not enable or fails
		$result = authenticateSQL(&$db, $username, $password);
	}
	if($result == true) {

		$query = "SELECT user.id, title, firstname, lastname, username, group_id, synergy_id, year, superusers.id AS su, password, email_pass, local_email, mail_handler
		FROM user LEFT JOIN superusers ON user.id = superusers.id WHERE username = '". mysql_real_escape_string( $username)."'";
		$user = $db->getRow($query);
	}
	
	//log the authentication attempt
	$ip = getIP(); 
	$loginlogquery = "INSERT INTO login_log (username, date, ip, login) 
			VALUES ('".mysql_real_escape_string($username)."', NOW(), '$ip'";

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
		$_SESSION['year'] = $user['year'];
		$_SESSION['local_email'] = $user['local_email'];
		$_SESSION['lasttime'] = mktime();
		$_SESSION['username'] = $user['username'];
		$_SESSION['password'] = $user['email_pass'];

		//remember users session (i.e. never let it expire)
		if(REMEMBERSESSION && $remember)
			$_SESSION['remember'] = 1;
		else
			$_SESSION['remember'] = 0;			
		
		//if no webmail enabled or user has elected to use external client set mailhandle to mailto:
		if(MAIL_enable == 0 || $user['mail_handler'] || (isParent($user['group_id']) && !$user['local_email']))
			$_SESSION['mailhandle'] = "mailto:";
		else
			$_SESSION['mailhandle'] = "/mail/createMail.php?replyto=";

		
			
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
		
		$db->query($campusquery);
		if($db->countRows() > 0) {
			while($campus = $db->getNextArray() ) {
				if($campus['campus'])
					$_SESSION['campus'][] = $campus['campus'];
			}
		}
		else
			$_SESSION['campus'][] = 1;

		if(SYNERGY_enable)
			setSynergyInfo(&$db);
		else {
			//testing parent system
			/*if($_SESSION['id'] == 7833) {
				$_SESSION['children'][] = array('id' => 4, 'sid' => 0, 'name' => 'Student Student');
				$_SESSION['children'][] = array('id' => 3, 'sid' => 0, 'name' => 'Test User');
			}*/
		}
		
		if(LDAP_enable)
			setLDAPInfo($username);

		$loginlogquery .= ", '1')";
		$db->query($loginlogquery);

		return true;
	}
	else
	{
		$loginlogquery .= ", '0')";
		$db->query($loginlogquery);
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
        $db->query($query);

	return 1;
}

function setLDAPInfo($username) {
	$ds=@ldap_connect(LDAP_host);
	$result = @ldap_search($ds, LDAP_base,'uid='.$username,array('dn','exchangeActive'));
	$entries = @ldap_get_entries($ds, $result);
	if($entries[0]['exchangeactive'][0] == 'TRUE')
		$_SESSION['exchange'] = true;

}

function setSynergyInfo(&$mydb)
{
    require_once('../library/synergetic-user.php');

    $synUser = new SynergeticUser;

	//setup a users enrolement for the session
	$semester = $synUser->getCurrentSemester();
	$_SESSION[ 'FileSemester'] = $semester[ 'FileSemester'];
	$_SESSION[ 'FileYear'] = $semester[ 'FileYear'];
	$synergy_id = $_SESSION['sid'];
	
	if(is_numeric($synergy_id) && $_SESSION['FileSemester'] && $_SESSION['FileYear']) {

		//if user is parent get information regarding enrolled students
		if( isParent($_SESSION['gid']))
		{
            //Updates parents email from synergetic
            $email = $synUser->getEmail($synergy_id);
			$mydb->update('user', 'id', array('id'=>$_SESSION['id'], 'alt_email'=>$email));

			$studentGroup = unserialize(STUDENTGROUPIDS);
			if($synergy_id) {
				$children = $synUser->getEnrolledStudents($synergy_id);
				if(is_array($children)) {
					foreach($children as $child)
					{
						$myquery = "SELECT * FROM user WHERE synergy_id = {$child['RelatedID']}";
						$portal = $mydb->getRow($myquery);
						$subjects = array();

						if($portal['id'])
						{
							$subjectList = $synUser->getSubjectList($child['RelatedID'], $portal['id']);
                            if( is_array($subjectList))
                            {
                                foreach($subjectList as $subject)
                                {
                                    //check subject exists.. we cant link to subjects that dont exist its ugly
                                    $myquery = "SELECT categoryID FROM categoryCode WHERE code = '".$subject['code']."'";
									if($result=$mydb->getRow($myquery) ) {
										if(  perm($result['categoryID'], HOMEPAGESERVICE) > 0 )  {
											$subject['id'] = $result['categoryID'];
											$subjects[] = $subject;
										}
									}
                                }
                            }

							$_SESSION['children'][] = array('id'=>$portal['id'], 'sid'=>$child['RelatedID'], 'gid'=>$portal['group_id'], 'name' => formatName($portal), 'subjects'=>$subjects);
						}
					}
				}
			}
		}
		else  //user is not parent so get timetable information
		{
			$subjectList = $synUser->getSubjectList($synergy_id, $_SESSION['id']);
			if( is_array($subjectList))
			{
				foreach($subjectList as $subject)
				{
					//check subject exists.. we cant link to subjects that dont exist its ugly
					$myquery = "SELECT categoryID FROM categoryCode WHERE code like '".$subject['code']."'";

					if($result=$mydb->getRow($myquery) )  {
						if(perm( $result['categoryID'], HOMEPAGESERVICE) > 0 ) {
							$subject['id'] = $result['categoryID'];
							$_SESSION['subjects'][] = $subject;
						}
					}

				}
				$_SESSION['timetable'] = 1;
			}
			else	
				$_SESSION['timetable'] = 0;
		}
	}
	
}

?>
