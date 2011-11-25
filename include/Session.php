<?php
require_once dirname(__FILE__) . '/DeprecatedDatabase.php';

class Session {
    function  __construct() {
      $this->db = new DeprecatedDatabase;
    }

	/**
	 * Function for returning user ip (tries to work around issues with proxies)
	 * Access it via Session class
	 *
	 * @return string
	 */
	public function getIP() {
		if ($_SERVER['HTTP_CLIENT_IP']) {
			$ip = $_SERVER['HTTP_CLIENT_IP'];
		}
		else if ($_SERVER['HTTP_X_FORWARDED_FOR']) {
			$ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
		}
		else if($_SERVER['REMOTE_ADDR']) {
			$ip = $_SERVER['REMOTE_ADDR'];
		}
		else {
			$ip = "UNKNOWN";
		}

		return $ip;
	}

    public function create($username) {
        require_once(dirname(__FILE__)  .'/../library/user.php');
        require_once(dirname(__FILE__)  .'/../library/group.php');
        $userHandler = new User;
        $groupHandler = new Group;

        //Clear all remenants of past sessions
        $this->destroy();

        //Start a new session
	    session_start();

        $user = $userHandler->getByUsername($username);

        $ip = Session::getIP();

        if(is_array($user)) {

            //register users id
            $_SESSION['title']      = $user['title'];
            $_SESSION['firstname']  = $user['firstname'];
            $_SESSION['lastname']   = $user['lastname'];
            $_SESSION['id']         = $user['uid'];
            $_SESSION['sid']        = $user['synergy_id'];
            $_SESSION['gid']        = $user['group_id'];
            $_SESSION['houseId']    = $user['house'];
            $_SESSION['year']       = $user['year'];
            $_SESSION['local_email']= $user['local_email'];
            $_SESSION['alt_email']  = $user['alt_email'];
            $_SESSION['email_pass'] = $user['email_pass'];
            $_SESSION['lasttime']   = mktime();
            $_SESSION['username']   = $user['username'];
            $_SESSION['password']   = $_POST['password']?$_POST['password']:$user['email_pass']; /* ALERT only works in some cases */
            $_SESSION['eportfolio_enabled'] = $user['eportfolio_enabled'];
            $_SESSION['ip'] = $ip;
            $_SESSION['campus'] = $userHandler->getUserCampus($user['uid']);

            //if no webmail enabled or user has elected to use external client set mailhandle to mailto:
            if(Cfg::get('mail_enable') == 0 || $user['mail_handler'] || ($groupHandler->isParent() && !$user['local_email']))
                $_SESSION['mailhandle'] = "mailto:";
            else
                $_SESSION['mailhandle'] = "/mail/createMail.php?replyto=";

            //superuser value, used to bypass most security checks
            if($user['su'] == 1)
                $_SESSION['su'] = 1;


            if(Cfg::get('external_enable'))
                $this->setSynergyInfo($userHandler);

           /* Old ormond code for extracting data from ldap
            * if(Cfg::get('ldap_enable'))
                $this->setLDAPInfo($username);
            */

            return true;
        }

    }

    public function destroy() {
        session_destroy();
    }

    public function authenticate($username, $password) {


        $result = false;

        $username = trim($username);
        $password = trim($password);

        if (get_magic_quotes_gpc()) {
            $username = stripslashes($username);
            $password = stripslashes($password);
        }

        if(empty($username) || empty($password) )
               return false;

        // Function to check username exists and password is correct
        if(Cfg::get('ldap_enable')) {
            $result = $this->authenticateLDAP($username, $password);

            //insert password into db to enable automatic login
            //$query = "UPDATE user SET email_pass = '$password' WHERE username = '".trim($username)."'";
            //$db->query($query);
        }

        if(!$result) {
            //fall back on SQL if ldap is not enable or fails
            $result = $this->authenticateSQL($username, $password);
	}

        $ip = Session::getIP();

        if ($result) {
            /* Log successful */
            $loginlogquery = "INSERT INTO login_log (username, date, ip, login)
                    VALUES ('".mysql_real_escape_string($username)."', NOW(), '$ip', '1')";
            $this->db->query($loginlogquery);
        } else {
            /* Log failure */
            $loginlogquery = "INSERT INTO login_log (username, date, ip, login)
                        VALUES ('".mysql_real_escape_string($username)."', NOW(), '$ip', '0')";
            $this->db->query($loginlogquery);
        }

        return $result;
    }

    private function authenticateLDAP($username, $password) {

        $ds=ldap_connect(Cfg::get('ldap_host'));
        ldap_set_option($ds, LDAP_OPT_PROTOCOL_VERSION, 3);
        ldap_set_option($ds, LDAP_OPT_REFERRALS, 0);
        ldap_bind($ds, $this->ldap_escape(Cfg::get('ldap_user')), $this->ldap_escape(Cfg::get('ldap_pass')));
        $searchString = "(".Cfg::get('ldap_username')."=".$this->ldap_escape($username).")";
        $result = ldap_search($ds,Cfg::get('ldap_base'), $searchString, array('dn'));
        if($result) {
                $entries = ldap_get_entries($ds, $result);
                $dn = $entries[0]['dn'];
                if($dn)
                        return @ldap_bind($ds, $dn, $this->ldap_escape($password));
        }
        ldap_unbind($ds);

        if(Cfg::get('ldap_host_alt'))  {
            //Try student server
            $ds=ldap_connect(Cfg::get('ldap_host_alt'));
            ldap_set_option($ds, LDAP_OPT_PROTOCOL_VERSION, 3);
            ldap_set_option($ds, LDAP_OPT_REFERRALS, 0);
            ldap_bind($ds, $this->ldap_escape(Cfg::get('ldap_user_alt')), $this->ldap_escape(Cfg::get('ldap_pass')));
            $result = ldap_search($ds,Cfg::get('ldap_base_alt'), $searchString, array('dn'));
            if($result) {
                $entries = ldap_get_entries($ds, $result);
                $dn = $entries[0]['dn'];
                if($dn)
                    return @ldap_bind($ds, $dn, $this->ldap_escape($password));
            }
        }

        return false;

    }

    private function authenticateSQL($username, $password) {
        // Function to check username exists and password is correct

        $query = "SELECT user.id FROM user WHERE username = '". mysql_real_escape_string($username)."'
        AND password = PASSWORD('".mysql_real_escape_string($password)."')";
        $this->db->query($query);
        $result = $this->db->countRows();
        //$user = $db->getNextArray();
        if($result == 1)
                return true;
        else
                return false;
    }

    private function ldap_escape($str, $for_dn = false)
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

    public function checkBruteForce()
    {
        // Function to check that host isnt brute forceing the login
        // Returns true if too many logins false if ok
        $ip = Session::getIP();
        $graceperiod = time() - Cfg::get('login_grace');

        $query = "SELECT id FROM login_log WHERE ip = '$ip' AND login = '0' AND date > FROM_UNIXTIME($graceperiod)";
        $this->db->query($query);

        if($this->db->countRows() < Cfg::get('login_allowed'))
                return false;
        else
                return true;
    }


    function setLDAPInfo($username) {
            $ds=@ldap_connect(LDAP_host);
            $result = @ldap_search($ds, LDAP_base,'uid='.$username,array('dn','exchangeActive'));
            $entries = @ldap_get_entries($ds, $result);
            if($entries[0]['exchangeactive'][0] == 'TRUE')
                    $_SESSION['exchange'] = true;

    }

    function setSynergyInfo ($userHandler) {
        require_once dirname(__FILE__) . '/../library/group.php';
        require_once dirname(__FILE__) . '/../library/category.php';
        $synUser = new External_User;
        $groupHandler = new Group;
        $catHandler = new Category;

        //setup a users enrolement for the session

        $semester = $synUser->getCurrentSemester();

        $synergy_id = $_SESSION['sid'];
        if ( $synergy_id ) {

            //if user is parent get information regarding enrolled students
            if ( $groupHandler->isParent() ) {
                if ( Cfg::get('external_sync_data') ) {
                    //Updates parents email from synergetic
                    $email = $synUser->getEmail($synergy_id);
                    $mydb->update('user', 'id', array( 'id' => $_SESSION['id'], 'alt_email' => $email ));
                }

                if ( $synergy_id ) {
                    $children = $synUser->getEnrolledStudents($synergy_id);

                    if ( is_array($children) ) {
                        foreach ( $children as $child ) {
                            $portal = $userHandler->getBySynergy($child['RelatedID']);
                            $subjects = array();

                            if ( $portal['uid'] ) {
                                $subjectList = $synUser->getSubjectList($child['RelatedID'], $portal['uid']);

                                if ( is_array($subjectList) ) {
                                    foreach ( $subjectList as $subject ) {
                                        //check subject exists.. we cant link to subjects that dont exist its ugly
                                        $cat = $catHandler->getByCode($subject['code']);

                                        if ( is_array($cat) && is_numeric($cat['id']) ) {
                                            if ( $groupHandler->getPermission($cat['id']) >= READ_ACCESS ) {
                                                $subject['id'] = $cat['categoryID'];
                                                $subjects[] = $subject;
                                            }
                                        }
                                    }
                                }

                                $_SESSION['children'][] = array(
                                    'id' => $portal['uid'], 'sid' => $child['RelatedID'], 'gid' => $portal['group_id'],
                                    'name' => $userHandler->formatName($portal), 'subjects' => $subjects
                                );
                            }
                        }
                    }
                }
            }
            else { //user is not parent so get timetable information
                $subjectList = $synUser->getSubjectList($synergy_id, $_SESSION['id']);

                $catList = array();
                if ( is_array($subjectList) ) {
                    foreach ( $subjectList as $subject ) {
                        //check subject exists.. we cant link to subjects that dont exist its ugly
                        $result = $catHandler->getByCode($subject['code']);
                        if ( is_array($result) && is_numeric($result['id']) ) {
                            if ( !in_array($result['id'], $catList) ) {
                                $subject['id'] = $result['id'];
                                $subject['name'] = $result['name'];

                                $_SESSION['subjects'][] = $subject;
                                $catList[] = $result['id']; //check for dupes
                            }
                        }
                    }
                    $_SESSION['timetable'] = 1;
                }
                else {
                    $_SESSION['timetable'] = 0;
                }

            }

        }
    }
}