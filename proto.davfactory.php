<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

use Schoolbox\Role;
use Schoolbox\User;
use Schoolbox\UserMapper;

require_once __DIR__ . '/include/Bootstrap.php';


class DavFactory {

    const FIELD_URI      = 'baseUri';
    const FIELD_USERNAME = 'userName';
    const FIELD_PASSWORD = 'password';
    const FIELD_PATHS    = 'paths';

    const CONDITION_ROLE   = 'role';
    const CONDITION_CAMPUS = 'campus';
    const CONDITION_YEAR   = 'yearlevel';

    /** @var array */
    protected $conditions;


    /**
     * @param string $uri
     * @param string $username
     * @param string $password
     * @param array  $paths
     */
    function __construct($uri, $username = '{:username}', $password = '{:password}', $paths = array('/'))
    {
        $this->conditions = array(
            'default' => array(
                self::FIELD_URI      => $uri,
                self::FIELD_USERNAME => $username,
                self::FIELD_PASSWORD => $password,
                self::FIELD_PATHS    => $paths
            )
        );
    }


    /**
     * @param User $user
     * @return array
     */
    public function buildSettings(User $user)
    {
        // increasing order of precedence
        $tests = array(
            self::CONDITION_ROLE   => $user->getRole()->getType(),
            self::CONDITION_CAMPUS => $user->getDefaultCampus()->getId(),
            self::CONDITION_YEAR   => $user->getYear()
        );

        // start with the default
        $ruleset = $this->conditions['default'];

        // find the rules that match this set
        foreach ($tests as $k => $v) {
            if (isset($this->conditions[$k][$v])) {
                foreach ($this->conditions[$k][$v] as $field => $value) {
                    $ruleset[$field] = $value;
                }
            }
        }

        // ensure settings START "well defined"
        $settings = array(
            self::FIELD_URI      => '',
            self::FIELD_USERNAME => '',
            self::FIELD_PASSWORD => '',
            self::FIELD_PATHS    => array('')
        );

        return $this->populateSettings($settings, $ruleset, $user);
    }


    /**
     * @param array $condition
     * @param array $value
     */
    protected function set(array $condition, array $value)
    {
        foreach ($condition as $inputName => $inputValue) {
            foreach ($value as $outputName => $outputValue) {
                $this->conditions[$inputName][$inputValue][$outputName] = $outputValue;
            }
        }
    }


    /**
     * @param array $settings
     * @param array $ruleset
     * @param User  $user
     * @return array
     */
    protected function populateSettings(array $settings, array $ruleset, User $user)
    {
        foreach ($ruleset as $field => $fieldValue) {
            $settings[$field] = $this->parseTokens($settings, $field, $fieldValue, $user);
        }

        return $settings;
    }


    /**
     * @param $settings
     * @param $field
     * @param $fieldValue
     * @param $user
     * @return mixed
     */
    protected function parseTokens($settings, $field, $fieldValue, $user)
    {
        if (is_array($fieldValue)) {
            foreach ($fieldValue as $subfield => $subfieldValue) {
                $settings[$field][$subfield] = $this->parseTokens($settings, $subfield, $subfieldValue, $user);
            }

        } else {
            $settings[$field] = $fieldValue;
            if (preg_match_all('`{:([a-z_][a-z0-9_]*)}`i', $fieldValue, $matches)) {
                foreach ($matches[1] as $match) {
                    // iterative replacement of tokens
                    $settings[$field] = str_replace("{:$match}", $this->renderToken($match, $user, $settings), $settings[$field]);
                }
            }
        }

        return $settings[$field];
    }


    /**
     * @param string $token
     * @param User   $user
     * @param array  $settings
     * @return string
     */
    protected function renderToken($token, $user, $settings)
    {
        switch ($token) {
            case 'username':    return $user->getUsername();
            case 'password':    return $user->getPassword();
            case 'lc_username': return strtolower($user->getUsername());
            case 'pc_username': return ucwords($user->getUsername());
            case 'YYYY':        return $user->getYear();
            case 'YY':          return str_pad($user->getYear(), 2, '0', STR_PAD_LEFT);
            case 'yr12_YY':     return 12 - $user->getYear() + date('y');
            case 'yr12_YYYY':   return 12 - $user->getYear() + date('Y');
            case 'yr7_YYYY':    return date('Y') - ($user->getYear() - 7);
            case 'yr7_YY':      return date('y') - ($user->getYear() - 7);
            case 'base_uri':    return $settings[self::FIELD_URI];
            default:
                return '';
        }
    }


    /****** INTERFACED SETTERS ******/

    /**
     * @param string|array $role
     * @param string $uri
     */
    public function setRoleUri($role, $uri)
    {
        $this->set(array(self::CONDITION_ROLE => $role), array(self::FIELD_URI => $uri));
    }


    /**
     * @param string|array $role
     * @param string $username
     */
    public function setRoleUsername($role, $username)
    {
        $this->set(array(self::CONDITION_ROLE => $role), array(self::FIELD_USERNAME => $username));
    }


    /**
     * @param string|array $role
     * @param string $password
     */
    public function setRolePassword($role, $password)
    {
        $this->set(array(self::CONDITION_ROLE => $role), array(self::FIELD_PASSWORD => $password));
    }


    /**
     * @param string|array $role
     * @param array $paths
     */
    public function setRolePaths($role, $paths)
    {
        $this->set(array(self::CONDITION_ROLE => $role), array(self::FIELD_PATHS => $paths));
    }


    /**
     * @param string|array $role
     * @param string $uri
     */
    public function setCampusUri($role, $uri)
    {
        $this->set(array(self::CONDITION_CAMPUS => $role), array(self::FIELD_URI => $uri));
    }


    /**
     * @param string|array $role
     * @param string $username
     */
    public function setCampusUsername($role, $username)
    {
        $this->set(array(self::CONDITION_CAMPUS => $role), array(self::FIELD_USERNAME => $username));
    }


    /**
     * @param string|array $role
     * @param string $password
     */
    public function setCampusPassword($role, $password)
    {
        $this->set(array(self::CONDITION_CAMPUS => $role), array(self::FIELD_PASSWORD => $password));
    }


    /**
     * @param string|array $role
     * @param array $paths
     */
    public function setCampusPaths($role, $paths)
    {
        $this->set(array(self::CONDITION_CAMPUS => $role), array(self::FIELD_PATHS => $paths));
    }


    /**
     * @param string|array $role
     * @param string $uri
     */
    public function setYearUri($role, $uri)
    {
        $this->set(array(self::CONDITION_YEAR => $role), array(self::FIELD_URI => $uri));
    }


    /**
     * @param string|array $role
     * @param string $username
     */
    public function setYearUsername($role, $username)
    {
        $this->set(array(self::CONDITION_YEAR => $role), array(self::FIELD_USERNAME => $username));
    }


    /**
     * @param string|array $role
     * @param string $password
     */
    public function setYearPassword($role, $password)
    {
        $this->set(array(self::CONDITION_YEAR => $role), array(self::FIELD_PASSWORD => $password));
    }


    /**
     * @param string|array $role
     * @param array $paths
     */
    public function setYearPaths($role, $paths)
    {
        $this->set(array(self::CONDITION_YEAR => $role), array(self::FIELD_PATHS => $paths));
    }

}



/* WENONA RULES */
$factory = new DavFactory('http://mercury/', 'wgs/{:username}');

$factory->setRoleUri(Role::STAFF, 'http://oxygen');
$factory->setRolePaths(Role::STAFF, array('/StaffHome/', '/StaffData/'));
$factory->setRolePaths(Role::STUDENT, array('/StudentHome/{:yr12_YY}/{:username}/', '/StudentData/'));


// test that stuff!
$roletypes = array(Role::STAFF, Role::STUDENT);

foreach ($roletypes as $type) {
    /** @var Role[] $roles */
    $roles = \Schoolbox\RoleMapper::getInstance()->findByColumn('group_type', $type);

    foreach ($roles as $role) {
        printf("Test member of %s: ", $role->getName());

        $userId   = UserMapper::getInstance()->findByColumn('group_id', $role->getId())->first()->getId();
        $user     = UserMapper::getInstance()->findById($userId);
        $settings = $factory->buildSettings($user);

        print_r($settings);
    }
}
