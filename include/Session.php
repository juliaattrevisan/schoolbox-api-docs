<?php

use Schoolbox\Notif\Notif;

class Session
{
    /**
     * Set useLoginLog to true to skip logging
     * in the loginLog log table
     *
     * @var bool
     */
    private $useLoginLog = true;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->db = DeprecatedDatabase::getInstance();
    }

    /**
     * Is this a valid session ID? If not, this session should be thrown out
     * and you should be given a new session ID.
     *
     * @param string $sessionId Usually from `session_id()` but could be a
     *                          value you want to set `session_id()` to.
     *
     * @return bool
     */
    public function validateSessionId($sessionId)
    {
        return !empty(trim($sessionId));
    }

    /**
     * Function for returning user ip (tries to work around issues with proxies)
     * Access it via Session class
     *
     * @return string
     */
    public function getIP()
    {
        if ($_SERVER['HTTP_CLIENT_IP']) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif ($_SERVER['HTTP_X_FORWARDED_FOR']) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif ($_SERVER['REMOTE_ADDR']) {
            $ip = $_SERVER['REMOTE_ADDR'];
        } else {
            $ip = "UNKNOWN";
        }

        return $ip;
    }

    public function create($username, $remember = false)
    {
        $userHandler = new User;
        $groupHandler = new Group;

        //Clear all remenants of past sessions
        $this->destroy();

        $user = $userHandler->getByUsername($username);

        $ip = Session::getIP();

        /* if user is found and enabled and session is started create our user record */
        if (is_array($user) && $user['enabled'] && session_start()) {
/* Store remember me cookie so user can return in the future */
            if ($remember == true) {
                $key = sha1(microtime(true).mt_rand(10000, 90000));
                $expire = strtotime('+2 month');
                setcookie('remember', $key, $expire, '/', $_SERVER['HTTP_HOST'], false, true);
                $redis = \Alaress\Redis::getClientInstance();
                $redis->executeCommand($redis->createCommand('set', array($key, $username) ));
                $redis->executeCommand($redis->createCommand('expire', array($key,  ($expire - $_SERVER['REQUEST_TIME']))));
            }
            //register users id
            $_SESSION['title'] = $user['title'];
            $_SESSION['firstname'] = $user['firstname'];
            $_SESSION['lastname'] = $user['lastname'];
            $_SESSION['id'] = $user['uid'];
            $_SESSION['sid'] = $user['synergy_id'];
            $_SESSION['gid'] = $user['group_id'];
            $_SESSION['houseId'] = $user['house'];
            $_SESSION['year'] = $user['year'];
            $_SESSION['local_email'] = $user['local_email'];
            $_SESSION['alt_email'] = $user['alt_email'];
            $_SESSION['webmail'] = $user['webmail'];
            $_SESSION['email_pass'] = $user['email_pass'];
            $_SESSION['key'] = $user['key'];
            $_SESSION['lasttime'] = time();
            $_SESSION['username'] = $user['username'];
            $_SESSION['password'] = $_POST['password'];
            $_SESSION['eportfolio_enabled'] = $user['eportfolio_enabled'];
            $_SESSION['ip'] = $ip;
            $_SESSION['campus'] = $userHandler->getUserCampus($user['uid']);
            $_SESSION['calendar_view'] = $user['calendar_view'];

            //if no mail enabled or user has elected to use external client set mailhandle to mailto:
            //TODO: remove this parent special config, but schools currently rely on it, need to update all parents to mail_handler = 1
            if (Cfg::get('mail_enable') == 0 || $user['mail_handler'] || ($groupHandler->isParent() && !$user['local_email'])) {
                $_SESSION['mailhandle'] = "mailto:";
            } else {
                $_SESSION['mailhandle'] = "/mail/createMail.php?replyto=";
            }

            //superuser value, used to bypass most security checks
            if ($user['su'] == 1) {
                $_SESSION['su'] = 1;
            }


            if (Cfg::get('external_enable')) {
                $this->setSynergyInfo($userHandler);
            }

            // add mobile session data
            $sbmAuth = new Authenticator();
            $result = $sbmAuth->appendSession($user['uid']);

            if ($this->getUseLoginLog()) {
                $notif = new Notif(Notif::TOPIC_AUTH);
                $notif->notice("Login success for $username", array(
                    'username' => $user['username'],
                    'ip'       => $ip,
                    'login'    => 1,
                ));
            }

            return $result;

        } else {
            return false;
        }
    }

    public function destroy()
    {
        @session_destroy();
    }

    /**
     * Is this user authenticated?
     *
     * Error types:
     * * User not found
     * * User found, but disabled
     * * User found, password is wrong
     *
     * @param string $username
     * @param string $password
     *
     * @return bool Was the user authenticated?
     * @throws Exception_Schoolbox_NotFound        if user doesn't exist
     * @throws Exception_Schoolbox_AccountDisabled if user account is disabled
     * @throws Exception_Schoolbox_AccessDenied    if password doesn't match
     */
    public function authenticate($username, $password)
    {

        // user must be a valid object (username exists) and also enabled
        $uMap = new \Schoolbox\UserMapper();
        $user = null;
        try {
            $user = $uMap->findOneByColumn('username', $username);
        } catch (Schoolbox\Mapper\Exception $e) {
            throw new Exception_Schoolbox_NotFound();
        }
        if (!($user instanceof \Schoolbox\User && $user->getEnabled())) {
            throw new Exception_Schoolbox_AccountDisabled();
        }

        $result = false;

        if (empty($username) || empty($password)) {
            throw new Exception_Schoolbox_AccessDenied();
        }

        // Function to check username exists and password is correct
        if (Cfg::get('ldap_enable')) {
            $result = $this->authenticateLDAP($username, $password);
        }

        if (!$result && Cfg::get('edumate_auth_enabled')) {
            $result = $this->authenticateEdumate($username, $password);
        }

        if (!$result) {
            //fall back on SQL if ldap is not enable or fails
            $result = $this->authenticateSQL($username, $password);
        }

        $ip = Session::getIP();

        if (!$result) {
            /* Log failure */
            $notif = new Notif(Notif::TOPIC_AUTH);
            $notif->notice("Login failure for ".$user->getUsername(), array(
                'username' => $user->getUsername(),
                'ip'       => $ip,
                'login'    => 0,
            ));
            throw new Exception_Schoolbox_AccessDenied();
        } else {
            // Store password as hash for DAV authentication later
            $this->db->update('user', 'username', array('username' => $username, 'hash' => md5($username . ':' . Cfg::get('site_location') . ':' . $password)));
        }

        return $result;
    }

    private function _authLDAP($username, $password, $ds, $ldap_user, $ldap_base, $version = 3, $referrals = 0)
    {
        // perform search for user in ldap server specified
        ldap_set_option($ds, LDAP_OPT_PROTOCOL_VERSION, $version);
        ldap_set_option($ds, LDAP_OPT_REFERRALS, $referrals);
        @ldap_bind($ds, $this->ldap_escape($ldap_user), utf8_encode(Cfg::get('ldap_pass')));
        $searchString = "(" . Cfg::get('ldap_username') . "=" . $this->ldap_escape($username) . ")";
        $ldap_search_filter = Cfg::get('ldap_search_filter');
        if (strlen($ldap_search_filter)) {
            $searchString = sprintf('(&(%s)%s)', $ldap_search_filter, $searchString);
        }
        $result = ldap_search($ds, $ldap_base, $searchString, array('dn'));
        // user exists
        if ($result) {
            $entries = ldap_get_entries($ds, $result);
            $dn = $entries[0]['dn'];
            // if the user exists
            if ($dn) {
                $bind = @ldap_bind($ds, $dn, utf8_encode($password));

                // failed to bind. find out why.
                if (!$bind) {
                    // get LDAP response: 0x0032 correlates to LDAP_OPT_DIAGNOSTIC_MESSAGE (but PHP doesn't map it by default)
                    if (ldap_get_option($ds, 0x0032, $ldap_error)) {
                        // extract error code
                        preg_match('/(data [0-9a-z]*)/', $ldap_error, $matches);
                        // if the preg_match finds an error formatted as expected
                        if ($matches[0]) {
                            $err_code = substr($matches[0], 5);
                            // error codes referenced from per http://wiki.service-now.com/index.php?title=LDAP_Error_Codes
                            switch ($err_code) {

                                case '701': // ACCOUNT_EXPIRED
                                case '533': // ACCOUNT_DISABLED
                                    // do not fall back. Throw error indicating expired account.
                                    $page = new SitePage(false);
                                    $errMsg = 'Your account has expired or been disabled.<br />
                                               Please contact a system administrator to regain access to your account.';
                                    $page->goLoginPage($errMsg);
                                    break;

                                case '773': // USER_MUST_RESET_PASSWORD
                                case '532': // PASSWORD_EXPIRED
                                    // do not fall back. Soft-redirect to password reset function.
                                    $page = new SitePage(false);
                                    $errMsg = 'Your password has expired and needs to be reset before you can log in.<br />';

                                    // site-specific handling for how to go about resetting a password
                                    switch (Cfg::get('site_location')) {

                                        case 'mgs':
                                            $errMsg .= 'Please visit <a href="https://owa.mgs.vic.edu.au/owa">owa.mgs.vic.edu.au/owa</a> to reset your password.';
                                            break;
                                        case 'bialik':
                                            $errMsg .= 'Please visit the school email system with this link:- <a href="https://webmail.bialik.vic.edu.au/owa">webmail.bialik.vic.edu.au</a> to reset your password.';
                                            break;
                                        default:
                                            $errMsg .= 'Please contact a system administrator to regain access to your account.';
                                            break;
                                    }
                                    $page->goLoginPage($errMsg);
                                    break;

                                case '52e': // INVALID_CREDENTIALS
                                case '49': // INVALID_CREDENTIALS
                                default: // SOME OTHER REASON...
                                    // default behaviour is to fall back onto SQL authentication.
                                    break;
                            }
                        }
                    }
                    @ldap_unbind($ds);
                    return false;
                } // successful connection.
                else {
                    @ldap_unbind($ds);
                    return true;
                }
            }
        }
        // user does not exist in LDAP
        @ldap_unbind($ds);
        return false;
    }

    private function authenticateLDAP($username, $password)
    {
        $ds = ldap_connect(Cfg::get('ldap_host'));

        // test authentication in primary server
        if ($this->_authLDAP($username, $password, $ds, Cfg::get('ldap_user'), Cfg::get('ldap_base'))) {
            return true;
        }

        // test authentication in secondary server
        if (Cfg::get('ldap_host_alt')) {
            $ds = ldap_connect(Cfg::get('ldap_host_alt'));
            if ($this->_authLDAP($username, $password, $ds, Cfg::get('ldap_user_alt'), Cfg::get('ldap_base_alt'))) {
                return true;
            }
        }

        // failed authentication
        return false;
    }
    private function authenticateSQL($username, $password)
    {
        // Function to check username exists and password is correct

        $query = "SELECT user.id FROM user WHERE username = '" . $this->db->quote($username) . "'
        AND password = PASSWORD('" . $this->db->quote($password) . "') AND `enabled` IS TRUE";
        $this->db->query($query);
        $result = $this->db->countRows();
        //$user = $db->getNextArray();
        if ($result == 1) {
            return true;
        } else {
            return false;
        }
    }


    protected function authenticateEdumate($username, $password)
    {
        //
        // Only for parents.
        $url = Cfg::get('edumate_auth_url');
        if (empty($url)) {
            return false;
        }
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 2);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(array("username" => $username, "password" => $password)));
        $result = curl_exec($ch);
        $code   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        unset($ch);
        //
        // Edumate always returns a string - "You have not been authenticated"
        // or a session ID which i'm guessing is either an int or hex number.
        return ($code < 400 && preg_match('/^[0-9]+$/', $result));
    }

    public function ldap_escape($str, $for_dn = false)
    {

        // see:
        // RFC2254
        // http://msdn.microsoft.com/en-us/library/ms675768(VS.85).aspx
        // http://www-03.ibm.com/systems/i/software/ldap/underdn.html

        if ($for_dn) {
            $metaChars = array(',', '=', '+', '<', '>', ';', '\\', '"', '#');
        } else {
            $metaChars = array('*', '(', ')', '\\', chr(0));
        }

        $quotedMetaChars = array();
        foreach ($metaChars as $key => $value) {
            $quotedMetaChars[$key] = '\\' . str_pad(dechex(ord($value)), 2, '0');
        }
        $str = str_replace($metaChars, $quotedMetaChars, $str); //replace them
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

        if ($this->db->countRows() < Cfg::get('login_allowed')) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * @param User $userHandler
     */
    function setSynergyInfo($userHandler)
    {
        $synUser = new External_User;
        $groupHandler = new Group;
        $folderMapper = new \Schoolbox\Resource\CategoryMapper();

        $synergy_id = $_SESSION['sid'];

        //
        // Children and subjects should be cleared before attempting to add
        // them. This prevents re-authentication (for example, from WebDAV) from
        // adding the same children and subjects again.
        unset($_SESSION['children'], $_SESSION['subjects']);

        if ($synergy_id) {
//if user is parent get information regarding enrolled students
            if ($groupHandler->isParent()) {
                $children = $synUser->getEnrolledStudents($synergy_id);

                /* Get subject list for each student */
                if (is_array($children)) {
                    foreach ($children as $child) {
                        $portal = $userHandler->getBySynergyAndGroup($child['RelatedID'], $groupHandler->getStudentGroupTypeIds());
                        $subjects = array();
                        $subjectIDList = array();

                        if ($portal['uid']) {
                            $subjectList = $synUser->getSubjectList($child['RelatedID'], $portal['uid']);

                            if (is_array($subjectList)) {
                                foreach ($subjectList as $subject) {
                                    //check subject exists.. we cant link to subjects that dont exist its ugly
                                    $cat = false;

                                    if ($subject['campusCode']) {
                                        //Check for subject with campus code
                                        try {
                                            $cat = $folderMapper->findByCode($subject['campusCode'].'/'.$subject['code']);
                                        } catch (\Exception $e) {
                                            //Try to fetch witout campus code
                                            try {
                                                $cat = $folderMapper->findByCode($subject['code']);
                                            } catch (\Exception $e) {
                                            }
                                        }
                                    }
                                    else {
                                        try {
                                            $cat = $folderMapper->findByCode($subject['code']);
                                        } catch (\Exception $e) {
                                        }
                                    }

                                    if ($cat instanceof \Schoolbox\Resource\Folder\Model) {
                                        if ($groupHandler->getPermission($cat->getId()) >= READ_ACCESS) {
                                            if (!in_array($cat->getId(), $subjectIDList)) {
                                                $subject['id'] = $cat->getId();
                                                $subject['name'] = $cat->getName();
                                                $subjects[] = $subject;
                                                $subjectIDList[] = $cat->getId(); //dont dupe ids in this list
                                            }
                                        }
                                    }
                                }
                            }

                            $_SESSION['children'][] = array(
                                'id' => $portal['uid'],
                                'sid' => $child['RelatedID'],
                                'gid' => $portal['group_id'],
                                'year' => $portal['year'],
                                'name' => $userHandler->formatName($portal),
                                'subjects' => $subjects
                            );
                        }
                    }
                }
            } else {
                //user is not parent so get timetable information
                $subjectList = $synUser->getSubjectList($synergy_id, $_SESSION['id']);
                $catList = array();
                if (is_array($subjectList)) {
                    foreach ($subjectList as $subject) {
                        //check subject exists.. we cant link to subjects that dont exist its ugly
                        $result = false;
                        // Ensure the link goes to an actual homepage, too
                        $homepageCode = '';
                        
                        if ($subject['campusCode']) {
                            //Check for subject with campus code
                            try {
                                $homepageCode = $subject['campusCode'].'/'.$subject['code'];
                                $result = $folderMapper->findByCode($homepageCode);
                            } catch (\Exception $e) {
                                // noop
                            }
                        }

                        //Try to fetch without campus code
                        if (!($result instanceof \Schoolbox\Resource\Folder\Model)) {
                            try {
                                $homepageCode = $subject['code'];
                                $result = $folderMapper->findByCode($subject['code']);
                            } catch (\Exception $e) {
                                // noop
                            }
                        }

                        if ($result instanceof \Schoolbox\Resource\Folder\Model) {
                            if (!in_array($result->getId(), $catList)) {
                                $subject['id'] = $result->getId();
                                $subject['name'] = $result->getName();
                                $subject['code'] = $homepageCode;
                                $_SESSION['subjects'][] = $subject;

                                $catList[] = $result->getId(); //check for dupes
                            }
                        }
                    }
                    $_SESSION['timetable'] = 1;
                } else {
                    $_SESSION['timetable'] = 0;
                }

            }

        }
    }

    /**
     * Enable login log logging
     */
    public function enableLoginLog()
    {
        $this->setUseLoginLog(true);
    }

    /**
     * Disable login log logging
     */
    public function disableLoginLog()
    {
        $this->setUseLoginLog(false);
    }

    /* Getters/Setters
     */

    /**
     * Set useLoginLog
     *
     * @param boolean $useLoginLog
     */
    private function setUseLoginLog($useLoginLog)
    {
        $this->useLoginLog = $useLoginLog;
    }

    /**
     * Get useLoginLog
     *
     * @return boolean
     */
    private function getUseLoginLog()
    {
        return $this->useLoginLog;
    }
}
