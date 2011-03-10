<?php

require_once dirname(__FILE__) . '/../include/model.php';
require_once dirname(__FILE__) . '/../library/group.php';
require_once dirname(__FILE__) . '/../library/homepage.php';

class Chat extends Model {
    function __construct ($config = array()) {
            $this->_table = "chat";
            $this->_index = "id";
            $this->_config = $config;

            $this->group = new Group();

            if ($this->_config['last_time'] == 'null') {
                $this->_config['last_time'] = new DateTime('-15 minutes');
            } else {
                $this->_config['last_time'] = new DateTime($this->_config['last_time']);
            }

            parent::Model();
    }

    /*
     * Creates a message in a specified chat module
     */
    function send ($data) {
        $data['time'] = new DateTime($data['time']);

        if (!is_object($this->_config['last_time']) || $data['time'] > $this->_config['last_time']) {
            $this->_config['last_time'] = $data['time'];
        }

        $data['time'] = $data['time']->format('Y-m-d H:i:s');

        return $this->create($data);
    }

    function system_send ($category_id, $type, $body = null, $time = null) {
        if ($time == null) {
            $time = new DateTime();
        }

        return $this->create(array(
            'category_id' => $category_id,
            'user_id'     => 0,
            'type'        => $type,
            'time'        => $time->format('Y-m-d H:i:s'),
            'body'        => $body
        ));
    }

    /*
     * Receives messages for a specific chat module
     */
    function receive ($category_id, $last_time) {
        $query = "SELECT title, firstname, lastname, chat.*
            FROM chat LEFT JOIN user ON chat.user_id = user.id
            WHERE `category_id` = $category_id";

        $query .= " AND `time` > '" . $this->_config['last_time']->format('Y-m-d H:i:s') . "'";
        $query .= " ORDER BY time ASC";

        $messages = $this->db->getAll($query);

        if (!empty($messages)) {
            $last_message = end($messages);
            $last_message_time = new DateTime($last_message['time']);

            if (!is_object($this->_config['last_time']) || $last_message_time > $this->_config['last_time']) {
                $this->_config['last_time'] = $last_message_time;
            }
        }

        return $messages;
    }

    function clear ($category_id) {
        $query = "DELETE FROM chat WHERE category_id = $category_id";

        return $this->db->query($query);
    }

    function poll ($category_id, $user_id = null) {
        if (is_numeric($user_id)) {
            $query = "INSERT INTO chat_poll (user_id, category_id, time)
                VALUES ($user_id, $category_id, NOW())
                ON DUPLICATE KEY UPDATE time = NOW()";
        }

        return $this->db->query($query);
    }

    function users ($category_id) {
        $last_time_limit = new DateTime('-6 seconds');
        
        $query = "SELECT title, firstname, lastname, chat_poll.*
            FROM chat_poll LEFT JOIN user ON chat_poll.user_id = user.id
            WHERE `category_id` = $category_id";

        $users_all = $this->db->getAll($query);

        foreach ($users_all as $user) {
            $user['time'] = new DateTime($user['time']);

            if ($user['time'] > $last_time_limit) {
                $users['active'][$user['user_id']] = $user;
            } else {
                $users['inactive'][$user['user_id']] = $user;
            }

            if ($this->group->getPermission($this->_config['category_id'], $user['user_id']) >= WRITE_ACCESS) {
                $users['admin'][$user['user_id']] = $user;
            }
        }

        return $users;
    }

    function users_remove ($users) {
        $query = "DELETE FROM chat_poll WHERE ";

        foreach ($users as $user) {
            if (empty($or)) {
                $query .= "`user_id` = " . $user['user_id'];

                $or = true;
            } else {
                $query .= " OR `user_id` = " . $user['user_id'];
            }
        }

        return $this->db->query($query);
    }

    function config ($config = null) {
        if ($config)
            $this->_config = $config;
        return $this->_config;
    }
}

?>