<?php

class Chat extends DeprecatedModel {
    function __construct () {
            $this->_table = "chat";
            $this->_index = "id";

            $this->group = new Group();

            parent::DeprecatedModel();
    }

    /* Get logs */
    function get_logs ($category_id, $last_poll = null) {
        $query = "SELECT title, firstname, lastname, c.*
                  FROM {$this->_table} c LEFT JOIN user ON c.user_id = user.id
                  WHERE `category_id` = $category_id";

        if ($last_poll instanceof DateTime) {
            $query .= " AND `time` > '" . $last_poll->format('Y-m-d H:i:s') . "'";
        }

        $query .= " ORDER BY time ASC";

        $logs = $this->db->getAll($query);

        if (!empty($logs)) {

            foreach ($logs as &$log) {
                $log_time = new DateTime($log['time']);

                $log['time_js'] = $log_time->format('D M j Y H:i:s \G\M\TO (T)'); // Thu Apr 14 2011 15:54:52 GMT+1000 (EST)
                $log['name'] = formatName($log);
                $log['user_perm'] = $this->group->getPermission($category_id, $log['user_id']);
                $log['type'] = scrubTEXT($log['type']);
                $log['body'] = scrubTEXT($log['body']);
            }

        }

        return $logs;
    }

    /* Create logs */
    function create_log ($category_id, $user_id, $body, $type = 'user') {
        $time = new DateTime();

        return $this->create(array(
            'category_id' => $category_id,
            'user_id'     => $user_id,
            'type'        => scrubTEXT($type),
            'time'        => $time->format('Y-m-d H:i:s'),
            'body'        => scrubTEXT($body)
        ));
    }

    function clear_log ($category_id) {
        $query = "DELETE FROM {$this->_table} WHERE category_id = $category_id";

        return $this->db->query($query);
    }

    function update_poll ($category_id, $user_id) {
        $query = "INSERT INTO chat_poll (user_id, category_id, time)
                  VALUES ($user_id, $category_id, NOW())
                  ON DUPLICATE KEY UPDATE time = NOW()";

        return $this->db->query($query);
    }

    function get_users ($category_id) {
        $query = "SELECT title, firstname, lastname, chat_poll.*
                  FROM chat_poll LEFT JOIN user ON chat_poll.user_id = user.id
                  WHERE `category_id` = $category_id";

        $users = $this->db->getAll($query);

        if (!empty($users)) {
            foreach ($users as &$user) {
                $user['name'] = formatName($user);
                $user['perm'] = $this->group->getPermission($category_id, $user['user_id']);
            }
        }

        return $users;
    }

    function prune_users ($category_id) {
        $time = new DateTime('-12 seconds');

        $query = "DELETE FROM chat_poll
                  WHERE time < '".$time->format('Y-m-d H:i:s')."'
                    AND category_id = $category_id";

        return $this->db->query($query);
    }
}

?>