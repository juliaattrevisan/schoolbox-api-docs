<?php

class Chat extends DeprecatedModel {
    function __construct () {
            $this->_table = "chat";
            $this->_index = "id";

            $this->group = new Group();

            parent::DeprecatedModel();
    }

    /* Get logs */
    function get_logs ($category_id, $last_poll = null, $not_user_id = 0) {
        $query = "SELECT title, firstname, lastname, c.*
                  FROM {$this->_table} c LEFT JOIN user ON c.user_id = user.id
                  WHERE `category_id` = $category_id";

        if ( !empty($not_user_id) ) {
            $query .= " AND `user_id` != $not_user_id";
        }

        if ($last_poll instanceof DateTime) {
            $query .= " AND `time` > '" . $last_poll->format('Y-m-d H:i:s') . "'";
        }

        $query .= " ORDER BY time ASC";

        $logs = $this->db->getAll($query);

        if (!empty($logs)) {
			$userHandler = new User();

            foreach ($logs as &$log) {
                $log_time = new DateTime($log['time']);

                $log['time_js'] = $log_time->format('D M j Y H:i:s \G\M\TO (T)'); // Thu Apr 14 2011 15:54:52 GMT+1000 (EST)
                $log['stamp'] = $log_time->format('Y-m-d H:i:s');
                $log['time'] = $log_time->format('h:i:s a');
                $log['name'] = $userHandler->formatName($log);
                $log['user_perm'] = $this->group->getPermission($category_id, $log['user_id']);
                $log['type'] = Scrub::text($log['type']);
                $log['body'] = Scrub::text($log['body']);
            }

        }

        return $logs;
    }

	/**
	 * Create a log entry which remembers what people typed.
	 *
	 * @param $category_id
	 * @param $user_id
	 * @param $body
	 * @param string $type
	 * @return int
	 */
	public function create_log($category_id, $user_id, $body, $type = 'user') {
		$now = new Alaress_Date_Format(time(), 'U');

		return $this->create(array(
			'category_id' => $category_id,
			'user_id' => $user_id,
			'type' => $type,
			'time' => $now->getDatabase(),
			'body' => $body,
		));
	}

/*  *** DEPRECATED ***
    function clear_log ($category_id) {
        $query = "DELETE FROM {$this->_table} WHERE category_id = $category_id";

        return $this->db->query($query);
    }
*/
    function update_poll ($category_id, $user_id) {
        $query = "INSERT INTO chat_poll (user_id, category_id, time)
                  VALUES ($user_id, $category_id, NOW())
                  ON DUPLICATE KEY UPDATE time = NOW()";

        return $this->db->query($query);
    }

    function get_users ($category_id) {
        // retrieve users as well as their membership. If no staff present, deactivate the chat.
        $query = "SELECT title, firstname, lastname, group_type, chat_poll.*
                  FROM chat_poll
                  LEFT JOIN `user` ON chat_poll.user_id = user.id
                  LEFT JOIN `groups` ON user.`group_id` = groups.`id`
                  WHERE `category_id` = $category_id";

        $users = $this->db->getAll($query);

        if (!empty($users)) {
			$userHandler = new User();

            foreach ($users as &$user) {
                $user['name'] = $userHandler->formatName($user);
                $user['perm'] = $this->group->getPermission($category_id, $user['user_id']);
            }
        }

        return $users;
    }

    function prune_users ($category_id) {
        $time = new DateTime('-9 seconds');

        $query = "DELETE FROM chat_poll
                  WHERE time < '".$time->format('Y-m-d H:i:s')."'
                    AND category_id = $category_id";

        return $this->db->query($query);
    }

	/**
	 * Removes content
	 *
	 * TODO: Refactor
	 *
	 * @param  $cid
	 * @return void
	 */
	public function homepageClean($cid) {
		$query = "DELETE FROM {$this->_table}  WHERE category_id = '{$cid}'";

		$this->db->query($query);

		$query = "DELETE FROM chat_poll  WHERE category_id = '{$cid}'";

		$this->db->query($query);
	}

    public function getOccupiedDateList($categoryID) {
        // check membership
        $groupHandler = new Group();

        $memberships = $groupHandler->getAllUserAccess($_SESSION['id']);

        if(!$groupHandler->isSuperuser()) {
            $permit = false;
            foreach ($memberships as $group) {
                if($categoryID == $group['sid']) {
                    $permit = true;
                    break;
                }
            }
            if(!$permit)
                return null;
        }

        $chats = $this->get_logs($categoryID);

        // go through and extract the years and months
        $occupiedDates = array();
        if($chats) {
            foreach($chats as $chat) {
                $df = new Alaress_Date_Format($chat['stamp'], 'y-m-d h:i:s');
                $occupation = array(
                    'year'  => $df->getUsingFormat('Y'),
                    'month' => $df->getUsingFormat('m')
                );
                if(!in_array($occupation, $occupiedDates)) {
                    $occupiedDates[] = $occupation;
                }
            }
        } else {
            return null;
        }

        // sort the generated list so the dates collate properly
        sort($occupiedDates);

        return $occupiedDates;
    }

    public function getChatListing($categoryID, $year = 0, $month = 0, $index = 0, $limit = 0) {
        $yearFilter = $year != 0 ? " AND YEAR(time) = $year " : '';
        $monthFilter = $month != 0 ? " AND MONTH(time) = $month " : '';

        $query = "SELECT *, DATE(`time`) AS `date`
                  FROM `{$this->_table}`
                  WHERE category_id = $categoryID $yearFilter $monthFilter";

        if($limit) {
            $query .= " LIMIT $index, $limit";
        }

        return $this->db->getAll($query);
    }
}
