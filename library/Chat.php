<?php

class Chat extends DeprecatedModel implements \Schoolbox\IHomepageCleanable
{
    /**
     * @var Group
     */
    private $group;

    /**
     * How long since the user's last action in chat before they are considered
     * inactive?
     */
    private const CHAT_POLL_EXPIRY = '-9 seconds';

    /**
     * Chat constructor.
     */
    public function __construct()
    {
        $this->_table = 'chat';
        $this->_index = 'id';

        $this->group = new Group();

        parent::__construct();
    }


    /**
     * Get logs
     *
     * @param int           $category_id
     * @param DateTime|null $last_poll
     * @param int           $not_user_id
     * @return array
     */
    public function getLogs($category_id, $last_poll = null, $not_user_id = 0)
    {
        $query = "
            SELECT
                title,
                firstname,
                preferred_name,
                lastname,
                synergy_id AS user_ext_id,
                if (user.pronoun, concat('(', pronoun.subjective, '/', pronoun.objective, ')'), '') as 'pronoun',
                c.*
            FROM {$this->_table} c
            LEFT JOIN user ON c.user_id = user.id
            LEFT JOIN pronoun ON user.pronoun = pronoun.id
            WHERE `category_id` = :categoryId
        ";
        $params = ['categoryId' => $category_id];

        if (!empty($not_user_id)) {
            $query .= " AND `user_id` != :notUserId";
            $params['notUserId'] = $not_user_id;
        }

        if ($last_poll instanceof DateTime) {
            $query .= " AND `time` > :lastPoll";
            $params['lastPoll'] = $last_poll->format('Y-m-d H:i:s');
        }

        $query .= " ORDER BY time ASC";

        $logs = $this->db->query($query, $params)->fetchAll();

        if (!empty($logs)) {
            $userHandler = new User();

            foreach ($logs as &$log) {
                try {
                    $log_time = new DateTime($log['time']);
                } catch (Exception $e) {
                    continue;
                }

                $log['time_js']   = $log_time->format('D M j Y H:i:s \G\M\TO (T)'); // Thu Apr 14 2011 15:54:52 GMT+1000 (EST)
                $log['stamp']     = $log_time->format('Y-m-d H:i:s');
                $log['time']      = $log_time->format('h:i:s a');
                $log['name']      = $userHandler->formatName($log);
                $log['user_perm'] = $this->group->getPermission($category_id, $log['user_id']);
                $log['type']      = Scrub::text($log['type']);
                $log['body']      = Scrub::text($log['body']);
            }
        }

        return $logs;
    }

    /**
     * Create a log entry which remembers what people typed.
     *
     * @param int    $category_id
     * @param int    $user_id
     * @param string $body
     * @param string $type
     * @return int
     * @throws InvalidArgumentException
     */
    public function createLog($category_id, $user_id, $body, $type = 'user')
    {
        $now = new Alaress_Date_Format(time(), 'U');

        return $this->create([
            'category_id' => $category_id,
            'user_id'     => $user_id,
            'type'        => $type,
            'time'        => $now->getDatabase(),
            'body'        => $body,
        ]);
    }


    /**
     * @param int $category_id
     * @param int $user_id
     * @return bool
     */
    public function updatePoll($category_id, $user_id): bool
    {
        if (!is_numeric($user_id)) {
            return false;
        }

        $query = "INSERT INTO chat_poll (user_id, category_id, time)
                  VALUES (?, ?, NOW())
                  ON DUPLICATE KEY UPDATE time = NOW()";

        $this->db->query($query, [$user_id, $category_id]);
        return true;
    }


    /**
     * @param int $category_id
     * @return array|bool
     */
    public function getUsers($category_id)
    {
        // retrieve users as well as their membership. If no staff present, deactivate the chat.
        $time   = new DateTime(self::CHAT_POLL_EXPIRY);
        $params = [
            $category_id,
            $time->format('Y-m-d H:i:s'),
        ];
        $query = "
            SELECT
                title,
                firstname,
                preferred_name,
                lastname,
                synergy_id AS user_ext_id,
                if (`user`.`pronoun`, concat('(', `pronoun`.`subjective`, '/', `pronoun`.`objective`, ')'), '') as `pronoun`,
                group_type,
                chat_poll.*
            FROM chat_poll
            LEFT JOIN `user` ON chat_poll.user_id = user.id
            LEFT JOIN `pronoun` ON `user`.`pronoun` = `pronoun`.`id`
            LEFT JOIN `groups` ON user.`group_id` = `groups`.`id`
            WHERE `category_id` = ?
            AND chat_poll.time >= ?
        ";

        $users = $this->db->query($query, $params)->fetchAll();

        if (!empty($users)) {
            $userHandler = new User();

            foreach ($users as &$user) {
                $user['name'] = $userHandler->formatName($user);
                $user['perm'] = $this->group->getPermission($category_id, $user['user_id']);
            }
        }

        return $users;
    }


    /**
     * @param int $category_id
     * @return bool
     * @throws Exception
     */
    public function pruneUsers($category_id): bool
    {
        $time = new DateTime(self::CHAT_POLL_EXPIRY);

        $query = "DELETE FROM chat_poll
                  WHERE time < '" . $time->format('Y-m-d H:i:s') . "'
                    AND category_id = $category_id";

        $this->db->query($query);
        return true;
    }

    /**
     * Removes content
     *
     * TODO: Refactor
     *
     * @param  int $cid
     * @return void
     */
    public function homepageClean($cid)
    {
        $query = "DELETE FROM {$this->_table}  WHERE category_id = '{$cid}'";

        $this->db->query($query);

        $query = "DELETE FROM chat_poll  WHERE category_id = '{$cid}'";

        $this->db->query($query);
    }

    /**
     * @param int $categoryID
     * @return array|null
     * @throws InvalidArgumentException
     */
    public function getOccupiedDateList($categoryID)
    {
        $memberships = $this->group->getAllUserAccess($_SESSION['id']);

        if (!$this->group->isSuperuser()) {
            if (!$memberships) {
                return null;
            }
            $permit = false;
            foreach ($memberships as $group) {
                if ($categoryID == $group['sid']) {
                    $permit = true;
                    break;
                }
            }
            if (!$permit) {
                return null;
            }
        }

        $chats = $this->getLogs($categoryID);

        // go through and extract the years and months
        $occupiedDates = [];
        if ($chats) {
            foreach ($chats as $chat) {
                $df         = new Alaress_Date_Format($chat['stamp'], 'Y-m-d H:i:s');
                $occupation = [
                    'year'  => $df->getYear(),
                    'month' => $df->getMonth(),
                ];
                if (!in_array($occupation, $occupiedDates)) {
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


    /**
     * @param int $categoryID
     * @param int $year
     * @param int $month
     * @param int $index
     * @param int $limit
     * @return array
     */
    public function getChatListing($categoryID, $year = 0, $month = 0, $index = 0, $limit = 0)
    {
        $yearFilter  = $year != 0 ? " AND YEAR(time) = $year " : '';
        $monthFilter = $month != 0 ? " AND MONTH(time) = $month " : '';

        $query = "SELECT c.*, DATE(`time`) AS `date`, u.synergy_id
                  FROM `{$this->_table}` c
                  LEFT JOIN `user` u on c.user_id = u.id
                  WHERE category_id = $categoryID $yearFilter $monthFilter";

        if ($limit) {
            $query .= " LIMIT $index, $limit";
        }

        return $this->db->query($query)->fetchAll();
    }
}
