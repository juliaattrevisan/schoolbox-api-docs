<?php
require_once dirname(__FILE__) .'/db.php';

class Session {
    function  __construct() {
      $this->db = new dbManager;
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

        $ip = getIP();

        if(is_array($user)) {

            //register users id
            $_SESSION['title']      = $user['title'];
            $_SESSION['firstname']  = $user['firstname'];
            $_SESSION['lastname']   = $user['lastname'];
            $_SESSION['id']         = $user['uid'];
            $_SESSION['sid']        = $user['synergy_id'];
            $_SESSION['gid']        = $user['group_id'];
            $_SESSION['year']       = $user['year'];
            $_SESSION['local_email']= $user['local_email'];
            $_SESSION['alt_email']  = $user['alt_email'];
            $_SESSION['lasttime']   = mktime();
            $_SESSION['username']   = $user['username'];
            $_SESSION['password']   = $password;
            $_SESSION['eportfolio_enabled'] = $user['eportfolio_enabled'];

            //if no webmail enabled or user has elected to use external client set mailhandle to mailto:
            if(MAIL_enable == 0 || $user['mail_handler'] || ($groupHandler->isParent($user['group_id']) && !$user['local_email']))
                $_SESSION['mailhandle'] = "mailto:";
            else
                $_SESSION['mailhandle'] = "/mail/createMail.php?replyto=";

            //superuser value, used to bypass most security checks
            if($user['su'] != "")
                $_SESSION['su'] = 1;

            //register there ip
            $_SESSION['ip'] = getIP();

            $_SESSION['campus'] = $userHandler->getUserCampus($user['id']);

            if(SYNERGY_enable)
                $this->setSynergyInfo($userHandler);

            if(LDAP_enable)
                $this->setLDAPInfo($username);


            /* Log successful */
            $loginlogquery = "INSERT INTO login_log (username, date, ip, login)
                    VALUES ('".mysql_real_escape_string($username)."', NOW(), '$ip', '1')";
            $this->db->query($loginlogquery);
            return true;
        }
        else {
            /* Log failure */
            $loginlogquery = "INSERT INTO login_log (username, date, ip, login)
                        VALUES ('".mysql_real_escape_string($username)."', NOW(), '$ip', '0')";
            $this->db->query($loginlogquery);
            return false;

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
        if(defined('LDAP_enable') && LDAP_enable) {
            $result = $this->authenticateLDAP($username, $password);

            //insert password into db to enable automatic login
            //$query = "UPDATE user SET email_pass = '$password' WHERE username = '".trim($username)."'";
            //$db->query($query);
        }

        if(!$result) {
            //fall back on SQL if ldap is not enable or fails
            $result = $this->authenticateSQL($username, $password);
	}


        return $result;
    }

    private function authenticateLDAP($username, $password) {

        $ds=ldap_connect(LDAP_host);
        ldap_set_option($ds, LDAP_OPT_PROTOCOL_VERSION, 3);
        ldap_set_option($ds, LDAP_OPT_REFERRALS, 0);
        ldap_bind($ds, $this->ldap_escape(LDAP_user), $this->ldap_escape(LDAP_pass));
        $searchString = "(SAMAccountName=".$this->ldap_escape($username).")";
        $result = ldap_search($ds,LDAP_base, $searchString, array('dn'));
        if($result) {
                $entries = ldap_get_entries($ds, $result);
                $dn = $entries[0]['dn'];
                if($dn)
                        return @ldap_bind($ds, $dn, $this->ldap_escape($password));
        }
        ldap_unbind($ds);

        if(defined('LDAP_host2'))  {
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
        $ip = getIP();
        $graceperiod = time() - LOGINGRACE;

        $query = "SELECT id FROM login_log WHERE ip = '$ip' AND login = '0' AND date > FROM_UNIXTIME($graceperiod)";
        $this->db->query($query);

        if($this->db->countRows() < LOGINALLOWED)
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

    function setSynergyInfo($userHandler) {
        require_once dirname(__FILE__) .'/../library/synergetic-user.php';
        require_once dirname(__FILE__) .'/../library/group.php';
        require_once dirname(__FILE__) .'/../library/category.php';
        $synUser = new SynergeticUser;
        $groupHandler = new Group;
        $catHandler = new Category;

        //setup a users enrolement for the session
        $semester = $synUser->getCurrentSemester();
        $_SESSION['FileSemester'] = $semester['FileSemester'];
        $_SESSION['FileYear'] = $semester['FileYear'];
        $synergy_id = $_SESSION['sid'];

        if (is_numeric($synergy_id) && $_SESSION['FileSemester'] && $_SESSION['FileYear']) {

            //if user is parent get information regarding enrolled students
            if ($groupHandler->isParent($_SESSION['gid'])) {
                if (defined('SYNERGY_sync_email') && SYNERGY_sync_email) {
                    //Updates parents email from synergetic
                    $email = $synUser->getEmail($synergy_id);
                    $mydb->update('user', 'id', array('id' => $_SESSION['id'], 'alt_email' => $email));
                }

                $studentGroup = unserialize(STUDENTGROUPIDS);
                if ($synergy_id) {
                    $children = $synUser->getEnrolledStudents($synergy_id);
                    if (is_array($children)) {
                        foreach ($children as $child) {
                            $portal = $userHandler->getBySynergy($child['ReleatedID']);
                            $subjects = array();

                            if ($portal['id']) {
                                $subjectList = $synUser->getSubjectList($child['RelatedID'], $portal['id']);
                                if (is_array($subjectList)) {
                                    foreach ($subjectList as $subject) {
                                        //check subject exists.. we cant link to subjects that dont exist its ugly
                                        $cat = $catHandler->getByCode($subject['code']);
                                        if(is_array($cat)) {
                                            if ($groupHandler->getPermission($cat['categoryID']) >= READ_ACCESS) {
                                                $subject['id'] = $cat['categoryID'];
                                                $subjects[] = $subject;
                                            }
                                        }
                                    }
                                }
                           }

                           $_SESSION['children'][] = array('id' => $portal['id'], 'sid' => $child['RelatedID'], 'gid' => $portal['group_id'], 'name' => formatName($portal), 'subjects' => $subjects);
                       }
                   }
               }
            } else { //user is not parent so get timetable information
                $subjectList = $synUser->getSubjectList($synergy_id, $_SESSION['id']);
                $catList = array();
                if (is_array($subjectList)) {
                    foreach ($subjectList as $subject) {
                        //check subject exists.. we cant link to subjects that dont exist its ugly
                        $result = $catHandler->getByCode($subject['code']);
                        if (is_array($result)) {
                            if (!in_array($result['categoryID'], $catList) && $groupHandler->getPermission($result['categoryID']) >= READ_ACCESS) {
                                $subject['id'] = $result['categoryID'];
                                $subject['name'] = $result['name'];

                                $_SESSION['subjects'][] = $subject;
                                $catList[] = $result['categoryID']; //check for dupes
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
}
?>
