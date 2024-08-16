<?php

use Schoolbox\Role;

/**
 * Ajax chat class - handles chat messages.
 * Moved from www/ajax/chat.php to here.
 *
 * @author    probably Matt Day <matt.day@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

class AjaxChat
{
    private $groupHandler;
    private $chatHandler;

    private $method;
    private $catID;
    private $componentID;
    private $userID;

    private $output;

    /**
     * @var Component_ConfigDefinition[]
     */
    private $config = array();

    /**
     * Creates the AJAX chat class.
     *
     * @param int   $user_id   The user that is using the chat
     * @param array $post_vars Vars for setting up the chat, usually passed
     *                         through $_POST.
     *                         * method: The method being called on the chat
     *                           (send, receive, poll, mute, config)
     *                         * category_id: The category the chat is in
     *                         * component_id: The ID of the chat component
     */
    public function __construct($user_id, $post_vars)
    {

        $this->method      = $post_vars['method'];
        $this->catID       = $post_vars['category_id'];
        $this->componentID = $post_vars['component_id'];
        $this->userID      = $user_id;

        $this->groupHandler = new Group();
        $this->chatHandler = new Chat();

        $this->output = [];

        if ($this->componentID) {
            $instance = Component_InstanceMapper::getInstance()->findByInstanceId($this->componentID, $this->catID);
            if ($instance instanceof Component_Instance) {
                foreach ($instance->getConfig() as $conf) {
                    /** @var Component_ConfigDefinition $conf */
                    $this->config[$conf->getKey()] = $conf;
                }
            }
        }
    }

    /**
     * Function that encapsulates the different methods of response
     *
     * @param array $post_vars Parameters passed in, usually by $_POST.
     *                         * body: the message to send (method=send only)
     *                         * last_poll: when messages were last asked for
     *                           (method=receive only)
     *                         * user_id: user to mute (method=mute only)
     */
    public function prepare($post_vars)
    {

        $time = new DateTime();
        $this->output = array(
            'time' => $time->format('D M j Y H:i:s \G\M\TO'),
        );

        $validMethods = array('send','receive','poll','mute','config');
        if (!(in_array($this->method, $validMethods) && $this->componentID)) {
            return;
        }

        switch ($this->method) {
            case 'send':
                $this->send($post_vars['body']);
                break;

            case 'receive':
                $this->receive($post_vars['last_poll']);
                break;

            case 'mute':
                $this->mute((int)$post_vars['user_id']);
                break;

            default:
                $m = $this->method;
                $this->$m();
        }
    }


    private function send($body)
    {

        if (!is_array($mutedUsers = unserialize($this->getConfig('muted_users') ?? ''))) {
            $mutedUsers = array();
        }

        if (!in_array($this->userID, $mutedUsers) && $this->getConfig('state') == 'Active') {
            $this->chatHandler->createLog($this->catID, $this->userID, $body);
        }
    }


    private function receive($lastPoll)
    {
        if (empty($lastPoll) || $lastPoll == 'null') {
            $log = $this->chatHandler->getLogs($this->catID, new DateTime('-30 minutes'));
        } else {
            $log = $this->chatHandler->getLogs($this->catID, new DateTime($lastPoll), $this->userID);
        }

        if (empty($log)) {
            $log = array();
        }
        $this->output['log'] = $log;
    }


    private function poll()
    {
        $this->chatHandler->updatePoll($this->catID, $this->userID);

        // Do not prune users anymore: get_users only gets new users
//        $this->chatHandler->pruneUsers($this->catID);

        // Return list of active users
        $users = $this->chatHandler->getUsers($this->catID);

        if (empty($users)) {
            $users = array();
        } else {
            $staffPresent = false;

            // ignore all of these checks if the chat is disabled.
            if ($this->getConfig('state') === 'Active' && !$this->groupHandler->isStaff()) {
                // If I am not personally a staff member,
                // iterate and find out if any of the users are
                foreach ($users as &$user) {
                    if ($user['group_type'] === Role::STAFF) {
                        $msg[] = $user['group_type'];

                        $staffPresent = true;
                    }
                    unset($user['group_type']);
                }
                unset($user);

                // deactivate the chat if no staff members
                if (!$staffPresent) {
                    $this->config['state']->setValue('Inactive');
                    $this->saveConfig();
                }
            }
        }

        $this->output['users']  = $users;
        $this->output['config'] = $this->getConfig();
    }


    private function mute($userID)
    {
        if (!is_array($mutedUsers = unserialize($this->getConfig('muted_users') ?? ''))) {
            $mutedUsers = array();
        }

        $idx = array_search($userID, $mutedUsers);

        if ($idx === false) {
            $mutedUsers[] = $userID;
        } else {
            unset($mutedUsers[$idx]);
        }

        $this->config['muted_users']->setValue(serialize($mutedUsers));

        $this->saveConfig();

        $this->output['config'] = $this->getConfig();
    }


    private function config()
    {
    }


    /**
     * Returns the requested config variable, or the entire config as an array if no argument passed
     * @param string $key
     * @return mixed
     */
    protected function getConfig($key = '')
    {
        if ($key) {
            return isset($this->config[$key]) ? $this->config[$key]->getValue() : false;
        }
        $return = array();
        foreach ($this->config as $key => $config) {
            if ($key == 'muted_users') {
                $return[$key] = unserialize($config->getValue() ?? '');
            } else {
                $return[$key] = $config->getValue();
            }
        }
        return $return;
    }


    protected function saveConfig()
    {
        /** @var Component_ConfigDefinition $definition */
        foreach ($this->config as $definition) {
            // this should be 1:1 relation, so return only the first instance of this definition
            /** @var Component_Config $confI */
            $confI = Component_ConfigMapper::getInstance()->findByColumnList(array(
                    'component_config_definition_id' => $definition->getId(),
                    'component_instance_id'          => $this->componentID
                ))->first();

            if (!($confI instanceof Component_Config && $confI->getId())) {
                $confI = new Component_Config();
                $confI->setConfigDefinitionId($definition->getId());
                $confI->setInstanceId($this->componentID);
            }
            $confI->setValue($definition->getValue());

            Component_ConfigMapper::getInstance()->save($confI);
        }
    }


    /**
     * Output whatever prepare() loaded into the output variable, and prevent further output.
     */
    public function respond()
    {
        //header("Content-type: application/json; charset=UTF-8");
        header("Content-type: text/plain; charset=UTF-8");
        die(json_encode($this->output));
    }
}
