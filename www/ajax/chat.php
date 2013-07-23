<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   2012 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 * 
 */

ini_set('display_errors',1);
error_reporting(E_ALL & ~E_NOTICE);
session_start();

require_once dirname(__FILE__) . '/../../include/Bootstrap.php';

class AjaxChat {

    private $page;
    private $groupHandler;
    private $chatHandler;
    private $homepageHandler;

    private $method;
    private $catID;
    private $componentID;
    private $userID;

    private $output;

    /**
     * @var array
     */
    private $config = array();

    /**
     * @var Component_Instance
     */
    private $instance;

    public function __construct($vars) {

        $this->method = $_POST['method'];
        $this->catID = $_POST['category_id'];
        $this->componentID = $_POST['component_id'];
        $this->userID = $_SESSION['id'];


        $this->page = new SitePage;
        $this->groupHandler = new Group();
        $this->chatHandler = new Chat();
        $this->homepageHandler = new Homepage($this->catID);

        $this->output = '';

        if($this->componentID) {
            $inst = Component_InstanceMapper::getInstance()->findByInstanceId($this->componentID);
            $this->instance = $inst[$this->componentID];

            // print_r($this->instance);
            foreach($this->instance->getConfig() as $conf) {
                $this->config[$conf['key']] = $conf;
            }
        }
    }

    /**
     * Function that encapsulates the different methods of response
     */
    public function prepare() {

        $time = new DateTime();
        $this->output = array(
            'time' => $time->format('D M j Y H:i:s \G\M\TO (T)'),
        );

        $validMethods = array('send','receive','poll','mute','config');
        if(!(in_array($this->method, $validMethods) && $this->componentID)) {
            return;
        }

        switch($this->method) {
            case 'send':
                $this->send($_POST['body']);
                break;

            case 'receive':
                $this->receive($_POST['last_poll']);
                break;

            case 'mute':
                $this->mute($_POST['user_id']);
                break;

            default:
                $m = $this->method;
                $this->$m();

        }

    }


    private function send($body) {

        if(!is_array($mutedUsers = unserialize($this->getConfig('muted_users')))) {
            $mutedUsers = array();
        }

        if (!in_array($this->userID, $mutedUsers) && $this->getConfig('state') == 'Active') {
            $this->chatHandler->create_log($this->catID, $this->userID, $body);
        }
    }


    private function receive($lastPoll) {
        if (empty($lastPoll) || $lastPoll == 'null') {
            $log = $this->chatHandler->get_logs($this->catID, new DateTime('-30 minutes'));
        }
        else {
            $log = $this->chatHandler->get_logs($this->catID, new DateTime($lastPoll), $this->userID);
        }

        if (empty($log)) {
            $log = array();
        }
        $this->output['log'] = $log;

    }


    private function poll() {
        $this->chatHandler->update_poll($this->catID, $this->userID);

        // Return list of active users
        $this->chatHandler->prune_users($this->catID);

        $users = $this->chatHandler->get_users($this->catID);

        if (empty($users)) {
            $users = array();

        } else {
            $staffPresent = false;

            // ignore all of these checks if the chat is disabled.
            if($this->getConfig('state') == 'Active') {

                // am I personally a staff member?
                if(!$this->groupHandler->isStaff()) {

                    // no? ok, iterate and find out if any of the users are
                    foreach($users as &$user) {
                        if($user['group_type'] == Group::STAFF_GROUP_TYPE) {

                            $msg[] = $user['group_type'];

                            $staffPresent = true;
                        }
                        unset($user['group_type']);
                    }

                    // deactivate the chat if no staff members
                    if(!$staffPresent) {
                        $this->config['state']->value = 'Inactive';
                    }
                }
                else {
                    // yes? set the flag
                    $this->config['state']->value = 'Active';
                }

                $this->saveConfig();
            }
        }

        $this->output['users'] = $users;
        $this->output['config'] = $this->getConfig();

    }


    private function mute($userID) {
        if(!is_array($mutedUsers = unserialize($this->getConfig('muted_users')))) {
            $mutedUsers = array();
        }

        $idx = array_search($userID, $mutedUsers);

        if ($idx === false) {
            $mutedUsers[] = $userID;
        } else {
            unset($mutedUsers[$idx]);
        }

        $this->config['muted_users']['value'] = serialize($mutedUsers);

        $this->saveConfig();

        $this->output['config'] = $this->getConfig();
    }


    private function config() {

    }


    /**
     * Returns the requested config variable, or the entire config as an array if no argument passed
     * @param string $key
     * @return array|bool
     */
    protected function getConfig($key = '') {
        if($key) {
            return isset($this->config[$key]) ? $this->config[$key]['value'] : false;
        }
        $return = array();
        foreach($this->config as $key => $config) {
            if($key == 'muted_users') {
                $return[$key] = unserialize($config['value']);
            }
            else {
                $return[$key] = $config['value'];
            }
        }
        return $return;
    }


    protected function saveConfig() {
        /** @var Component_ConfigDefinition $definition */
        foreach ($this->config as $definition) {
            // this should be 1:1 relation, so return only the first instance of this definition
            $confI = Component_ConfigMapper::getInstance()->findByColumnList(array(
                'component_config_definition_id' => $definition->getId(),
                'component_instance_id'          => $this->instance->getId()
            ))->first();

            if(!($confI->id)) {
                $confI = new Component_Config();
                $confI->setConfigDefinitionId($definition->getId());
                $confI->setInstanceId($this->instance->getId());
            }
            $confI->setValue($definition->getValue());

            Component_ConfigMapper::getInstance()->save($confI);
        }
    }


    /**
     * Output whatever prepare() loaded into the output variable, and prevent further output.
     */
    public function respond() {
        //header("Content-type: application/json; charset=UTF-8");
        header("Content-type: text/plain; charset=UTF-8");
        die(json_encode($this->output));
    }

}

$ac = new AjaxChat($_POST);
$ac->prepare();
$ac->respond();

?>