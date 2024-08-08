<?php

/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use League\Flysystem\FilesystemException;
use Ramsey\Uuid\Uuid;
use Schoolbox\API\Token\JwtParser\SelfIssued;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Notif\Notif;
use Schoolbox\Notif\Notifactory;
use Schoolbox\Application\Exception\AccountDisabled as SchoolboxAccountDisabledException;
use Schoolbox\Application\Exception\AccessDenied as SchoolboxAccessDeniedException;
use Schoolbox\Application\Exception\NotFound as SchoolboxNotFoundException;
use Schoolbox\Application\Exception\PasswordExpired as SchoolboxPasswordExpiredException;
use Schoolbox\Resource\Storage\Mapper as StorageMapper;
use Schoolbox\UserMapper;
use Schoolbox\User;
use Schoolbox\User\Log\Mapper as LoginLogMapper;

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
     * If this is set to true in $_SESSION, track_ip check will be bypassed
     */
    public const BYPASS_IP_TRACKING = 'bypass_ip_tracking';

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
     * @param bool|null   $bypassIpTracking
     * @return bool Whether new Session started for proposed user
     */
    public function create(string $username, ?string $jwtId = null, ?bool $bypassIpTracking = false): bool
    {
        $userMapper   = UserMapper::getInstance();
        $groupHandler = new Group();

        // Clear all remnants of past sessions, except identifier
        $sessionIdentifier = $_SESSION['identifier'] ?? null;
        $_SESSION          = [];

        $user = $userMapper->findByUsername($username);

        $ip = self::getIP();

        /* if user is found and enabled and session is started create our user record */
        if ($user instanceof User && $user->getEnabled()) {
            /* Start by creating a fresh ID to ensure ID has not been injected */
            session_regenerate_id(true);

            //register users id
            $_SESSION['id']       = $user->getId();
            $_SESSION['sid']      = $user->getExternalId();
            $_SESSION['lasttime'] = time();
            $_SESSION['username'] = $user->getUsername();
            if (
                Config::get(ConfigMapper::NAME_EXTERNAL_TYPE) === \External\IConnector::EXTERNAL_TYPE_SYNERGETIC
                && !empty(Config::get('external_portal'))
            ) {
                $_SESSION['password'] = $_POST['password'] ?? null;
            }
            $_SESSION['ip'] = $ip;

            // Create a random session ID as well:
            // however, this survives user authentication and re-authentication
            $sessionIdentifier ??= Uuid::uuid4()->__toString();
            $_SESSION['identifier'] = $sessionIdentifier;
            if ($bypassIpTracking) {
                $_SESSION[self::BYPASS_IP_TRACKING] = true;
            }

            // add mobile session data
            $sbmAuth = new Authenticator();
            // this populates user's subjectCollection
            $result = $sbmAuth->appendSession($user->getId());

            //if no mail enabled or user has elected to use external client set mailhandle to mailto:
            //TODO: remove this parent special config, but schools currently rely on it, need to update all parents to mail_handler = 1
            if (Config::get('mail_enable') == 0 || $user->getUseInternalMail() || ($groupHandler->isParent() && !$user->getLocalEmail())) {
                $_SESSION['mailhandle'] = "mailto:";
            } else {
                $_SESSION['mailhandle'] = "/mail/create?replyto=";
            }

            //superuser value, used to bypass most security checks
            if ($user->isSuperuser()) {
                $_SESSION['su'] = 1;
            }

            // If external is off, or user is not a parent, user explicitly has no children (as opposed to unknown)
            if (Config::get('external_enable')) {
                $this->setSynergyInfo($user);
            }

            if ($this->getUseLoginLog()) {
                $notif = Notifactory::create(Notif::TOPIC_AUTH);
                $notif->notice("Login success for $username", [
                    'username'   => $user->getUsername(),
                    'ip'         => $ip,
                    'login'      => 1,
                    'jwtId'      => $jwtId,
                    'identifier' => $_SESSION['identifier'],
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
    public function authenticate(
        string $username,
        #[SensitiveParameter] string $password,
    ): ?User {
        if (empty($username) || empty($password)) {
            throw new SchoolboxAccessDeniedException();
        }

        $notif              = Notifactory::create(Notif::TOPIC_AUTH);
        $notifFailureParams = [
            'username'   => $username,
            'ip'         => Session::getIP(),
            'login'      => 0,
            'jwtId'      => null,
            'identifier' => null,
        ];

        $userMapper = UserMapper::getInstance();

        // Cannot use findOneByColumn() as that returns deleted users which may mask extant user.
        $user = $userMapper->findByColumnList(['username' => $username], ['deleted' => false])->first();

        // Fall back to using email address
        if (!$user instanceof \Schoolbox\User) {
            if (Config::get(ConfigMapper::NAME_AUTH_EMAIL_ENABLED)) {
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
        if (Config::get('webdav_auth_type') === 'digest') {
            $userMapper->storeHash($user, md5($username . ':' . Config::get('site_school_code') . ':' . $password));
        }

        // ensure a key is set on login
        if (!$user->getKey()) {
            $user->setKey(substr(md5(uniqid() . time()), 0, 25));
            $userMapper->save($user);
        }

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
     * @return mixed The connection: a resource in PHP 8.0 or earlier, a
     *               `\LDAP\Connection` in 8.1 or later, or false if connection
     *               failed.
     * @throws MapperException
     */
    public function ldapConnect(
        string $host,
        string $username,
        #[SensitiveParameter] string $password,
        ?string $ldapCaCertHash,
    ) {
        if ($host) {
            if ($ldapCaCertHash) {
                // Copy this file to local cache somewhere (stream_dir I guess)
                $storageMapper   = StorageMapper::getInstance();
                $ldapCaCert      = $storageMapper->findByHash($ldapCaCertHash);
                $filesystemProxy = $storageMapper->getStorageFilesystemProxy();
                $ldapCaCertPath  = sprintf(
                    '%s/ldap-ca-cert-%s-%s',
                    Config::get('stream_dir'),
                    INSTANCE_ID,
                    $ldapCaCert->getHash()
                );
                if (!file_exists($ldapCaCertPath)) {
                    try {
                        file_put_contents($ldapCaCertPath, $filesystemProxy->read($ldapCaCert));
                    } catch (FilesystemException $e) {
                        //can't read cert, just continue without the certificate
                    }
                }

                // Now if the file _does_ exist: set ldap options
                if (file_exists($ldapCaCertPath)) {
                    ldap_set_option(null, LDAP_OPT_X_TLS_CACERTDIR, dirname($ldapCaCertPath));
                    ldap_set_option(null, LDAP_OPT_X_TLS_CACERTFILE, $ldapCaCertPath);
                }
            }
            $ds = ldap_connect($host);
            if ($ds) {
                ldap_set_option($ds, LDAP_OPT_PROTOCOL_VERSION, 3);
                ldap_set_option($ds, LDAP_OPT_REFERRALS, 0);
                $bind = @ldap_bind(
                    $ds,
                    $this->ldapEscape($username),
                    mb_convert_encoding($password, 'UTF-8', 'ISO-8859-1'),
                );
                if ($bind) {
                    return $ds;
                }
            }
        }
        return null;
    }


    /**
     * Authenticates a username/password against the provided LDAP connection.
     *
     * @param mixed  $ds            An LDAP connection (whether a resource or
     *                              `\LDAP\Connection` instance), if created
     * @param string $username      The username to authenticate in the LDAP
     *                              connection
     * @param string $password      The password for user $username
     * @param string $userDn        What is the distinguished name of the
     *                              directory containing usernames?
     * @param string $usernameField Which field in $userDn contains the
     *                              username?
     * @param string $searchFilter  What other filters get applied to the
     *                              username field search?
     * @return bool
     * @throws SchoolboxAccountDisabledException
     * @throws SchoolboxPasswordExpiredException
     */
    private function ldapAuth(
        mixed $ds,
        string $username,
        #[SensitiveParameter] string $password,
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
                $bind = @ldap_bind($ds, $dn, mb_convert_encoding($password, 'UTF-8', 'ISO-8859-1'));

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
    private function authenticateLDAP(string $username, #[SensitiveParameter] string $password): bool
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
                $ds
                && $this->ldapAuth(
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
    private function authenticateSQL(string $username, #[SensitiveParameter] string $password): bool
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
        $ip             = Session::getIP();
        $graceperiod    = Alaress_Date_Format::now()->addSeconds(0 - Config::get('login_grace'));
        $loginLogMapper = LoginLogMapper::getInstance();
        $pb             = $loginLogMapper->getPredicateBuilder()
            ->equals('ip', $ip)
            ->isFalse('login')
            ->greaterThan('date', $graceperiod->getUsingFormat(Alaress_Date_Format::DATABASE_FORMAT));
        $loginLogCount  = $loginLogMapper->countByPredicates($pb);

        if ($loginLogCount < Config::get('login_allowed')) {
            return false;
        } else {
            return true;
        }
    }


    /**
     * @param User       $user
     */
    private function setSynergyInfo(User $user)
    {
        $synUser = new External_User();
        $subjectMapper = new \Schoolbox\External\SubjectMapper();
        $externalUser = new \Schoolbox\External\User(
            ConfigMapper::getInstance()->getConfigLoader()->get('external_type'),
            new \External_Class(),
            $synUser,
            UserMapper::getInstance()
        );
        //load all relationships for the current user

        $synergyId = $_SESSION['sid'];

        // initialize children, as there are places using getChildren to check permission
        $externalUser->loadChildren([$user]);

        //
        // Children and subjects should be cleared before attempting to add
        // them. This prevents re-authentication (for example, from WebDAV) from
        // adding the same children and subjects again.
        unset($_SESSION['children'], $_SESSION['subjects']);

        if ($synergyId) {
            if ($user->getRole()->isParent()) {
                $children = $user->getChildren();

                /* Get subject list for each student */
                if ($children->count()) {
                    foreach ($children as $childObject) {
                        if (!$childObject->getHideTimetable()) {
                            $childObject->setSubjectCollection($subjectMapper->findAllForUser($childObject, $user));
                        } else {
                            $childObject->setSubjectCollection(new \Schoolbox\External\SubjectCollection());
                        }
                    }
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
