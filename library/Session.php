<?php

use Schoolbox\Config\ConfigMapper;
use Schoolbox\GenericCollection;
use Schoolbox\Notif\Notif;
use Schoolbox\Notif\Notifactory;
use Schoolbox\Application\Exception\AccountDisabled as SchoolboxAccountDisabledException;
use Schoolbox\Application\Exception\AccessDenied as SchoolboxAccessDeniedException;
use Schoolbox\Application\Exception\NotFound as SchoolboxNotFoundException;
use Schoolbox\Application\Exception\PasswordExpired as SchoolboxPasswordExpiredException;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\Role;
use Schoolbox\UserMapper;
use Schoolbox\User;

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
     * @var Access
     */
    private $access;

    /**
     * @param Access|null $access
     * @throws Exception
     */
    public function __construct(Access $access = null)
    {
        $this->db     = DeprecatedDatabase::getInstance();
        $this->access = $access ?? Access::getInstance();
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
    public static function getIP()
    {
        if (isset($_SERVER['HTTP_CLIENT_IP']) && !empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR']) && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif (isset($_SERVER['REMOTE_ADDR']) && !empty($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        } else {
            $ip = "UNKNOWN";
        }

        return $ip;
    }

    /**
     * @param string $username New user's user.username
     * @param bool $remember Whether to store a 'remember' me cookie
     * @return bool Whether new Session started for proposed user
     */
    public function create($username, $remember = false)
    {
        $userHandler  = new \User;
        $userMapper   = UserMapper::getInstance();
        $groupHandler = new Group;

        //Clear all remnants of past sessions
        $this->destroy();

        $user = $userMapper->findByUsername($username);

        $ip = self::getIP();

        /* if user is found and enabled and session is started create our user record */
        if ($user instanceof User && $user->getEnabled() && session_start()) {

            /* Start by creating a fresh ID to ensure ID has not been injected */
            session_regenerate_id();

            /* Store remember me cookie so user can return in the future */

            if ($remember == true) {
                $key = sha1(microtime(true).mt_rand(10000, 90000));
                $expire = strtotime('+2 month');
                $cookieParams = session_get_cookie_params();
                setcookie(
                    'remember',
                    $key,
                    $expire,
                    $cookieParams['path'],
                    $cookieParams['domain'],
                    $cookieParams['secure'],
                    $cookieParams['httponly']
                );
                $redis = \Alaress\Redis::getClientInstance();
                $redis->executeCommand($redis->createCommand('set', array($key, $username) ));
                $redis->executeCommand($redis->createCommand('expire', array($key,  ($expire - $_SERVER['REQUEST_TIME']))));
            }

            //register users id
            $_SESSION['title']              = $user->getTitle();
            $_SESSION['firstname']          = $user->getFirstname();
            $_SESSION['lastname']           = $user->getLastname();
            $_SESSION['id']                 = $user->getId();
            $_SESSION['sid']                = $user->getExternalId();
            $_SESSION['gid']                = $user->getRole() ? $user->getRole()->getId() : null;
            $_SESSION['houseId']            = $user->getSchoolHouse() ? $user->getSchoolHouse()->getId() : null;
            $_SESSION['year']               = $user->getYear();
            $_SESSION['local_email']        = $user->getLocalEmail();
            $_SESSION['alt_email']          = $user->getAltEmail();
            $_SESSION['webmail']            = $user->getWebmail();
            $_SESSION['key']                = $user->getKey();
            $_SESSION['lasttime']           = time();
            $_SESSION['username']           = $user->getUsername();
            $_SESSION['password']           = $_POST['password'] ?? null;
            $_SESSION['eportfolio_enabled'] = $user->getEportfolioEnable();
            $_SESSION['ip']                 = $ip;
            $_SESSION['campus']             = $userHandler->getUserCampus($user->getId());

            // add mobile session data
            $sbmAuth = new Authenticator();
            $result = $sbmAuth->appendSession($user->getId());

            //if no mail enabled or user has elected to use external client set mailhandle to mailto:
            //TODO: remove this parent special config, but schools currently rely on it, need to update all parents to mail_handler = 1
            if (Cfg::get('mail_enable') == 0 || $user->getUseInternalMail() || ($groupHandler->isParent() && !$user->getLocalEmail())) {
                $_SESSION['mailhandle'] = "mailto:";
            } else {
                $_SESSION['mailhandle'] = "/mail/createMail.php?replyto=";
            }

            //superuser value, used to bypass most security checks
            if ($user->isSuperuser()) {
                $_SESSION['su'] = 1;
            }

            // If external is off, or user is not a parent, user explicitly has no children (as opposed to unknown)
            $user->setChildren(new GenericCollection(User::class));
            if (Cfg::get('external_enable')) {
                $this->setSynergyInfo($userMapper, $user);
            }

            if ($this->getUseLoginLog()) {
                $notif = Notifactory::create(Notif::TOPIC_AUTH);
                $notif->notice("Login success for $username", array(
                    'username' => $user->getUsername(),
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
     * @return \Schoolbox\User|null Was the user authenticated, if so return the user
     * @throws SchoolboxAccessDeniedException    if password doesn't match
     * @throws SchoolboxAccountDisabledException if user account is disabled
     * @throws SchoolboxNotFoundException        if user doesn't exist
     * @throws SchoolboxPasswordExpiredException if ldap password expired
     */
    public function authenticate($username, $password)
    {
        if (empty($username) || empty($password)) {
            throw new SchoolboxAccessDeniedException();
        }

        $ip    = Session::getIP();
        $notif = Notifactory::create(Notif::TOPIC_AUTH);
        $userMapper = UserMapper::getInstance();

        // Cannot use findOneByColumn() as that returns deleted users which may mask extant user.
        $user = $userMapper->findByColumnList(['username' => $username], ['deleted' => false])->first();

        // Fall back to using email address
        if(!$user instanceof \Schoolbox\User) {
            if (Cfg::get('auth_email_enabled')) {
                $user = $userMapper->findByColumnList(['alt_email' => $username], ['deleted' => false])->first();
                //Override username with actual username, rather than email address so user can proceed
                if($user instanceof \Schoolbox\User) {
                    $username = $user->getUsername();
                }
            }
        }

        if(!$user instanceof \Schoolbox\User) {
            // Log the fact that user doesn't exist
            $notif->notice("Login failure for non-existent user ".$username, array(
                'username' => $username,
                'ip'       => $ip,
                'login'    => 0,
            ));
            throw new SchoolboxNotFoundException();
        }

        if (!($user->getEnabled())) {
            // Log the fact that user is not enabled
            $notif->notice("Login failure for non-enabled user ".$user->getUsername(), array(
                'username' => $user->getUsername(),
                'ip'       => $ip,
                'login'    => 0,
            ));
            throw new SchoolboxAccountDisabledException();
        }

        $result = false;

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

        if (!$result) {
            /* Log failure */
            $notif->notice("Login failure for ".$user->getUsername(), array(
                'username' => $user->getUsername(),
                'ip'       => $ip,
                'login'    => 0,
            ));
            throw new SchoolboxAccessDeniedException();
        } else {
            // Store password as hash for DAV authentication later
            $save = [
                'username' => $username,
                'hash'     => md5($username . ':' . Cfg::get('site_location') . ':' . $password)
            ];

            // ensure a key is set on login
            if (!$user->getKey()) {
                $save['key'] = substr(md5(uniqid() . time()), 0, 25);
            }

            $this->db->update('user', 'username', $save);
        }

        if($result) {
            return $user;
        }
        else {
            return null;
        }
    }

    /**
     * @param string    $username
     * @param string    $password
     * @param string    $ds
     * @param string    $ldap_user
     * @param string    $ldap_base
     * @param int       $version
     * @param int       $referrals
     * @throws SchoolboxAccountDisabledException
     * @throws SchoolboxPasswordExpiredException
     * @return bool
     *
     */
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
                                    throw new SchoolboxAccountDisabledException('Your account has expired or been disabled.');

                                case '773': // USER_MUST_RESET_PASSWORD
                                case '532': // PASSWORD_EXPIRED
                                    // do not fall back. Soft-redirect to password reset function.
                                    throw new SchoolboxPasswordExpiredException('Your password has expired and needs to be reset before you can log in.');
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

        // test authentication in tertiary server
        if (Cfg::get('ldap_host_ter')) {
            $ds = ldap_connect(Cfg::get('ldap_host_ter'));
            if ($this->_authLDAP($username, $password, $ds, Cfg::get('ldap_user_ter'), Cfg::get('ldap_base_ter'))) {
                return true;
            }
        }

        // failed authentication
        return false;
    }

    /**
     * @param string $username
     * @param string $password
     * @return bool
     */
    private function authenticateSQL($username, $password)
    {
        $passwordValidator = new \Schoolbox\User\Password\Validator(
            \Schoolbox\UserMapper::getInstance(),
            new \Alaress\Password\Service()
        );
        return $passwordValidator->checkPassword($username, $password);
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
        $ip          = Session::getIP();
        $graceperiod = Alaress_Date_Format::now()->addSeconds(0 - Config::get('login_grace'));

        $query = "
            SELECT id
            FROM login_log
            WHERE ip    = '{$this->db->quote($ip)}'
            AND   login = '0'
            AND   date  > '{$this->db->quote($graceperiod->getUsingFormat(Alaress_Date_Format::DATABASE_FORMAT))}'
        ";
        $this->db->query($query);

        if ($this->db->countRows() < Cfg::get('login_allowed')) {
            return false;
        } else {
            return true;
        }
    }


    /**
     * @param UserMapper $userMapper
     * @param User       $user
     */
    private function setSynergyInfo(UserMapper $userMapper, User $user)
    {
        $synUser = new External_User;
        $folderMapper = new \Schoolbox\Resource\Folder\Mapper();

        $synergy_id = $_SESSION['sid'];

        //
        // Children and subjects should be cleared before attempting to add
        // them. This prevents re-authentication (for example, from WebDAV) from
        // adding the same children and subjects again.
        unset($_SESSION['children'], $_SESSION['subjects']);

        if ($synergy_id) {
//if user is parent get information regarding enrolled students
            if ($user->getRole()->isParent()) {
                $children = $synUser->getEnrolledStudents($synergy_id);

                /* Get subject list for each student */
                if (is_array($children)) {
                    foreach ($children as $child) {

                        try {
                            $childObject = $userMapper->findByExternalId($child['RelatedID']);
                        } catch (\Schoolbox\Mapper\Exception $e) {
                            continue;
                        }
                        $childObject->setParents(new GenericCollection(User::class));
                        $user->getChildren()[$childObject->getId()] = $childObject;
                        $childObject->getParents()[$user->getId()]  = $user;
                        if (!$childObject->getHideTimetable()) {
                            $subjectList = $synUser->getSubjectList($childObject);
                        } else {
                            $subjectList = false;
                        }

                        $subjects = array();
                        $subjectIDList = array();

                        if (is_array($subjectList)) {
                            foreach ($subjectList as $subject) {
                                $cat = $folderMapper->findForCampusAndClass($subject['campusCode'], $subject['code']);

                                if ($cat instanceof Folder && !\in_array($cat->getId(), $subjectIDList, false)) {
                                    $subject['id']   = $cat->getId();
                                    $subject['name'] = $cat->getName();
                                    $subject['perm'] = $this->access->getFolderAccess($_SESSION['user'], $cat->getId());
                                    // TODO Lawn mow student subjects and parent children subjects to have same format and semantics.
                                    // TODO Currently, a student's $subject['code'] is the SB folder code,
                                    // TODO whereas a parent's child $subject['code'] is the SIS code.
                                    $subject['matchedCode'] = $cat->getMatchedCode();
                                    $subjects[]      = $subject;
                                    $subjectIDList[] = $cat->getId(); //dont dupe ids in this list
                                }
                            }
                        }

                        $_SESSION['children'][] = array(
                            'id' => $childObject->getId(),
                            'sid' => $childObject->getExternalId(),
                            'gid' => $childObject->getRole()->getId(),
                            'year' => $childObject->getYear(),
                            'name' => $childObject->getFullName(),
                            'subjects' => $subjects
                        );

                    }
                }
            } else {
                //user is not parent so get timetable information
                if(!$_SESSION['user']->getHideTimetable()) {
                    $subjectList = $synUser->getSubjectList($_SESSION['user']);
                }
                else {
                    $subjectList = false;
                }
                $catList = array();
                if (is_array($subjectList)) {

                    // declare the array
                    $_SESSION['subjects'] = [];

                    foreach ($subjectList as $subject) {
                        //check subject exists.. we cant link to subjects that dont exist its ugly
                        // Ensure the link goes to an actual homepage, too
                        $result = $folderMapper->findForCampusAndClass($subject['campusCode'], $subject['code']);

                        if ($result instanceof Folder) {
                            if (!in_array($result->getId(), $catList)) {
                                $subject['id'] = $result->getId();
                                $subject['name'] = $result->getName();
                                $subject['perm'] = $this->access->getRoleTypeAccess($user->getRole()->getType());
                                $subject['code'] = $result->getMatchedCode();
                                $_SESSION['subjects'][] = $subject;

                                $catList[] = $result->getId(); //check for dupes
                            }
                        }
                    }

                    if (is_array($_SESSION['subjects'])) {
                        usort($_SESSION['subjects'], function ($a, $b) {
                            return strnatcmp($a['name'], $b['name']);
                        });
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


    /**
     * Returns the level of access granted in Schoolbox when a roleType has SIS involvement
     * with a folder.
     *
     * @param string $roleType
     * @return int
     * @deprecated This doesn't belong here. Please don't use this method.
     */
    private function getSisFolderAccess(string $roleType): int
    {
        if ($roleType === Role::STAFF) {
            return UserAccess::ACCESS_WRITE;
        }
        if ($roleType === Role::STUDENT && ConfigMapper::getInstance()->get('allow_student_class_access')) {
            return UserAccess::ACCESS_READ;
        }
        return UserAccess::ACCESS_DENY;
    }
}
