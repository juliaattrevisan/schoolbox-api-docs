<?php

/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Schoolbox\GenericCollection;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Notif\Notif;
use Schoolbox\Notif\Notifactory;
use Schoolbox\Application\Exception\AccountDisabled as SchoolboxAccountDisabledException;
use Schoolbox\Application\Exception\AccessDenied as SchoolboxAccessDeniedException;
use Schoolbox\Application\Exception\NotFound as SchoolboxNotFoundException;
use Schoolbox\Application\Exception\PasswordExpired as SchoolboxPasswordExpiredException;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\Resource\Storage\Mapper as StorageMapper;
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
     * @var DeprecatedDatabase
     */
    private $db;

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
        // Sourced from https://stackoverflow.com/questions/1634782/what-is-the-most-accurate-way-to-retrieve-a-users-correct-ip-address-in-php?rq=1
        foreach (array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR') as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip); // just to be safe

                    if (filter_var($ip, FILTER_VALIDATE_IP) !== false) {
                        return $ip;
                    }
                }
            }
        }
        return '';
    }

    /**
     * Create a session.
     *
     * Note the `$remember` param is gone: remembering a login is now handled
     * outside of this class, in `Schoolbox\Auth\Remember`.
     *
     * @param string      $username New user's user.username
     * @param string|null $jwtId
     * @return bool Whether new Session started for proposed user
     * @throws MapperException
     */
    public function create(string $username, ?string $jwtId = null): bool
    {
        $userHandler  = new \User();
        $userMapper   = UserMapper::getInstance();
        $groupHandler = new Group();

        //Clear all remnants of past sessions
        $this->destroy();

        $user = $userMapper->findByUsername($username);

        $ip = self::getIP();

        /* if user is found and enabled and session is started create our user record */
        if ($user instanceof User && $user->getEnabled() && session_start()) {
            /* Start by creating a fresh ID to ensure ID has not been injected */
            session_regenerate_id();

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
            if (Config::get('mail_enable') == 0 || $user->getUseInternalMail() || ($groupHandler->isParent() && !$user->getLocalEmail())) {
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
            if (Config::get('external_enable')) {
                $this->setSynergyInfo($userMapper, $user);
            }

            if ($this->getUseLoginLog()) {
                $notif = Notifactory::create(Notif::TOPIC_AUTH);
                $notif->notice("Login success for $username", [
                    'username' => $user->getUsername(),
                    'ip'       => $ip,
                    'login'    => 1,
                    'jwtId'    => $jwtId
                ]);
            }

            return $result;
        } else {
            return false;
        }
    }

    /**
     * Destroys session
     */
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

        $notif              = Notifactory::create(Notif::TOPIC_AUTH);
        $notifFailureParams = [
            'username' => $username,
            'ip'       => Session::getIP(),
            'login'    => 0,
            'jwtId'    => null,
        ];

        $userMapper = UserMapper::getInstance();

        // Cannot use findOneByColumn() as that returns deleted users which may mask extant user.
        $user = $userMapper->findByColumnList(['username' => $username], ['deleted' => false])->first();

        // Fall back to using email address
        if (!$user instanceof \Schoolbox\User) {
            if (Config::get('auth_email_enabled')) {
                $user = $userMapper->findByColumnList(['alt_email' => $username], ['deleted' => false])->first();
                //Override username with actual username, rather than email address so user can proceed
                if ($user instanceof \Schoolbox\User) {
                    $username = $user->getUsername();
                }
            }
        }

        if (!$user instanceof \Schoolbox\User) {
            // Log the fact that user doesn't exist
            $notif->notice("Login failure for non-existent user " . $username, $notifFailureParams);
            throw new SchoolboxNotFoundException();
        }

        if (!($user->getEnabled())) {
            // Log the fact that user is not enabled
            $notif->notice("Login failure for non-enabled user " . $user->getUsername(), $notifFailureParams);
            throw new SchoolboxAccountDisabledException();
        }

        $result = false;

        // Function to check username exists and password is correct
        if (Config::get('ldap_enable')) {
            $result = $this->authenticateLDAP($username, $password);
        }

        if (!$result) {
            //fall back on SQL if ldap is not enable or fails
            $result = $this->authenticateSQL($username, $password);
        }

        if (!$result) {
            /* Log failure */
            $notif->notice("Login failure for " . $user->getUsername(), $notifFailureParams);
            throw new SchoolboxAccessDeniedException();
        }
        // Store password as hash for DAV authentication later
        $save = [
            'username' => $username,
            'hash'     => md5($username . ':' . Config::get('site_school_code') . ':' . $password),
        ];

        // ensure a key is set on login
        if (!$user->getKey()) {
            $save['key'] = substr(md5(uniqid() . time()), 0, 25);
        }

        $this->db->update('user', 'username', $save);
        return $user;
    }


    /**
     * Connect to the provided LDAP host.
     *
     * @param string      $host           The LDAP host's URL
     * @param string      $username       A username for auth to the LDAP host.
     *                                    This is distinct from the username that is
     *                                    being searched for within LDAP.
     * @param string      $password       A password for auth to the LDAP host.
     *                                    This is distinct from the password for the
     *                                    username that is being searched for.
     * @param string|null $ldapCaCertHash If provided, hash of a local path to a cert.
     *                                    This must be a local file, because the
     *                                    LDAP extension needs to access this file.
     * @return bool|resource The connection, or false if none could be created.
     * @throws MapperException
     */
    public function ldapConnect(string $host, string $username, string $password, ?string $ldapCaCertHash)
    {
        if ($host) {
            if ($ldapCaCertHash) {
                // Copy this file to local cache somewhere (stream_dir I guess)
                $ldapCaCert     = StorageMapper::getInstance()->findByHash($ldapCaCertHash);
                $ldapCaCertPath = sprintf(
                    '%s/ldap-ca-cert-%s-%s',
                    Config::get('stream_dir'),
                    INSTANCE_ID,
                    $ldapCaCert->getHash()
                );
                if (!file_exists($ldapCaCertPath)) {
                    file_put_contents($ldapCaCertPath, $ldapCaCert->read());
                }
                ldap_set_option(null, LDAP_OPT_X_TLS_CACERTDIR, dirname($ldapCaCertPath));
                ldap_set_option(null, LDAP_OPT_X_TLS_CACERTFILE, $ldapCaCertPath);
            }
            $ds = ldap_connect($host);
            if ($ds) {
                ldap_set_option($ds, LDAP_OPT_PROTOCOL_VERSION, 3);
                ldap_set_option($ds, LDAP_OPT_REFERRALS, 0);
                $bind = @ldap_bind($ds, $this->ldapEscape($username), utf8_encode($password));
                if ($bind) {
                    return $ds;
                }
            }
        }
        return false;
    }


    /**
     * Authenticates a username/password against the provided LDAP connection.
     *
     * @param bool|resource $ds            An LDAP connection
     * @param string        $username      The username to authenticate in the
     *                                     LDAP connection
     * @param string        $password      The password for user $username
     * @param string        $userDn        What is the distinguished name of the
     *                                     directory which contains usernames?
     * @param string        $usernameField Which field in $userDn contains the
     *                                     username?
     * @param string        $searchFilter  What other filters should be applied
     *                                     to the username field search?
     * @return bool
     * @throws SchoolboxAccountDisabledException
     * @throws SchoolboxPasswordExpiredException
     */
    private function ldapAuth(
        $ds,
        string $username,
        string $password,
        string $userDn,
        string $usernameField,
        string $searchFilter
    ) {
        // perform search for user in ldap server specified
        if (!$ds) {
            return false;
        }
        $searchString = "(" . $usernameField . "=" . $this->ldapEscape($username) . ")";
        if ($searchFilter !== '') {
            $searchString = sprintf('(&(%s)%s)', $searchFilter, $searchString);
        }
        $result = ldap_search($ds, $userDn, $searchString, array('dn'));
        // user exists
        if ($result) {
            $entries = ldap_get_entries($ds, $result);
            $dn = $entries[0]['dn'] ?? null;
            // if the user exists
            if ($dn) {
                $bind = @ldap_bind($ds, $dn, utf8_encode($password));

                // failed to bind. find out why.
                if (!$bind) {
                    // get LDAP response
                    if (ldap_get_option($ds, LDAP_OPT_DIAGNOSTIC_MESSAGE, $ldap_error)) {
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
                } else {
                    // successful connection.
                    @ldap_unbind($ds);
                    return true;
                }
            }
        }
        // user does not exist in LDAP
        @ldap_unbind($ds);
        return false;
    }

    /**
     * @param string $username
     * @param string $password
     * @return bool
     * @throws MapperException
     */
    private function authenticateLDAP($username, $password)
    {
        $ldapConfigs = [
            [
                'host'       => Config::get('ldap_host'),
                'username'   => Config::get('ldap_user'),
                'searchBase' => Config::get('ldap_base'),
            ],
            [
                'host'       => Config::get('ldap_host_alt'),
                'username'   => Config::get('ldap_user_alt'),
                'searchBase' => Config::get('ldap_base_alt'),
            ],
            [
                'host'       => Config::get('ldap_host_ter'),
                'username'   => Config::get('ldap_user_ter'),
                'searchBase' => Config::get('ldap_base_ter'),
            ],
        ];
        $ldapPassword      = Config::get('ldap_pass');
        $ldapUsernameField = Config::get('ldap_username');
        $ldapSearchFilter  = Config::get('ldap_search_filter');
        $ldapCaCertHash    = Config::get('ldap_ca_certificate');
        foreach ($ldapConfigs as $ldapConfig) {
            $ds = $this->ldapConnect($ldapConfig['host'], $ldapConfig['username'], $ldapPassword, $ldapCaCertHash);
            if (
                $ds && $this->ldapAuth(
                    $ds,
                    $username,
                    $password,
                    $ldapConfig['searchBase'],
                    $ldapUsernameField,
                    $ldapSearchFilter
                )
            ) {
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

    /**
     * @param string $str
     * @param bool   $for_dn
     * @return array|string|string[]
     */
    public function ldapEscape($str, $for_dn = false)
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

    /**
     * @return bool
     */
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

        if ($this->db->countRows() < Config::get('login_allowed')) {
            return false;
        } else {
            return true;
        }
    }


    /**
     * @param UserMapper $userMapper
     * @param User       $user
     * @throws MapperException
     */
    private function setSynergyInfo(UserMapper $userMapper, User $user)
    {
        $synUser = new External_User();
        $folderMapper = \Schoolbox\Resource\Folder\Mapper::getInstance();

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
                        } catch (MapperException $e) {
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
                                    $subject['perm'] = $this->access->getFolderAccess($user, $cat->getId());
                                    // TODO Lawn mow student subjects and parent children subjects to have same format and semantics.
                                    // TODO Currently, a student's $subject['code'] is the SB folder code,
                                    // TODO whereas a parent's child $subject['code'] is the SIS code.
                                    $subject['matchedCode'] = $cat->getMatchedCode();
                                    $subjects[]      = $subject;
                                    $subjectIDList[] = $cat->getId(); //dont dupe ids in this list
                                }
                            }
                        }

                        // sorts in the same order as the profile and sidemenu, which is “Type”, “Class Name”, “Class Code”
                        uasort(
                            $subjects,
                            static function (array $a, array $b): int {
                                $type = strnatcasecmp($a['classType'], $b['classType']);
                                if ($type === 0) {
                                    $name = strnatcasecmp($a['name'], $b['name']);
                                    if ($name === 0) {
                                        return strnatcasecmp($a['code'], $b['code']);
                                    }
                                    return $name;
                                }
                                return $type;
                            }
                        );

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
                if (!$user->getHideTimetable()) {
                    $subjectList = $synUser->getSubjectList($_SESSION['user']);
                } else {
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
                                $subject['perm'] = $this->access->getFolderAccess($user, $result->getId());
                                ;
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
}
