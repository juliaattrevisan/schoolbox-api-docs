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

		parent::Model();
	}

    /*
     * Creates a message in a specified chat module
     */
    function send ($data) {
        return $this->create($data);
    }

    /*
     * Receives messages for a specific chat module
     */
    function receive ($category_id, $timestamp_modify = null, $ignore_user = null) {
        $query = "SELECT title, firstname, lastname, chat.*
            FROM chat LEFT JOIN user ON chat.user_id = user.id
            WHERE `category_id` = $category_id";

        if (is_numeric($ignore_user)) {
            $query .= " AND `user_id` != '" . $ignore_user . "'";
        }

        if (is_string($timestamp_modify)) {
            $timestamp = new DateTime();
            $timestamp->modify($timestamp_modify);

            $query .= " AND `timestamp` > '" . $timestamp->format('Y-m-d H:i:s') . "'";
        } elseif (is_object($timestamp_modify)) {
            $query .= " AND `timestamp` > '" . $timestamp_modify->format('Y-m-d H:i:s') . "'";
        }

        $query .= " ORDER BY timestamp ASC";

        return $this->db->getAll($query);
    }

    function poll ($category_id, $user_id = null, $time_modify = null) {
        if (is_numeric($user_id)) {
            $query = "INSERT INTO chat_poll (user_id, category_id, time, active)
                VALUES ($user_id, $category_id, NOW(), true)
                ON DUPLICATE KEY UPDATE time = NOW()";
        }

        $this->db->query($query);

        $query = "SELECT title, firstname, lastname, chat_poll.*
            FROM chat_poll LEFT JOIN user ON chat_poll.user_id = user.id
            WHERE `category_id` = $category_id";

        if(is_string($time_modify)) {
            $time = new DateTime();
            $time->modify($time_modify);

            $query .= " AND `time` > '" . $time->format('Y-m-d H:i:s') . "'";
        }

        return $this->db->getAll($query);
    }

    function config ($config = null) {
        if ($config)
            $this->_config = $config;
        return $this->_config;
    }
}

?>