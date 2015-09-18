<?php

class User extends DeprecatedModel
{

    /**
     * A copy of the user notifier - it is called when a user changes.
     *
     * @var Schoolbox\UserNotifier
     */
    protected $userNotifier;

    public function User()
    {
        $this->_table = "user";
        $this->_index = "id";
        parent::DeprecatedModel();
    }


    /**
     * @return Schoolbox\UserNotifier
     */
    protected function getUserNotifier()
    {
        // only load this if you *actually* need it
        if (empty($this->userNotifier)) {
            $this->userNotifier = new Schoolbox\UserNotifier(new Schoolbox\Config\Loader());
        }
        return $this->userNotifier;
    }


    /**
     * Gets the ID associated with a particular campus name. Returns false if
     * the campus name was not matched.
     *
     * @param  string $name
     *
     * @return int
     */
    public function getCampusID($name)
    {
        $query = "SELECT id FROM luCampus WHERE name LIKE '$name'";
        return $this->db->getOne($query);
    }

    public function getGroupID($name)
    {
        $query = "SELECT id FROM groups WHERE name LIKE '$name'";
        return $this->db->getOne($query);
    }

    public function getAllInCategory($categoryID)
    {
        $query = "SELECT title, id as uid, firstname, lastname, type, username, local_email, alt_email, synergy_id
                  FROM user, userAccess
                  WHERE id = uid AND service = '" . HOMEPAGE_SERVICE . "' AND sid = '" . $categoryID . "' AND type > 0
                  ORDER BY lastname, firstname";
        return $this->db->getAll($query);
    }

    public function getManagers($categoryID)
    {
        $query = "SELECT title, id as uid, firstname, lastname, type, username, local_email, alt_email, synergy_id FROM user, userAccess
        WHERE id = uid AND service = '" . HOMEPAGE_SERVICE . "' AND sid = '" . $categoryID . "' and type in(3,2) ORDER BY lastname, firstname";
        return $this->db->getAll($query);
    }

    public function getByID($id)
    {
        $query = "SELECT user.id as uid, title, firstname, lastname, username, groups.name AS gname, group_id, year, `key`, hash, eportfolio_enabled,
                        synergy_id, local_email, alt_email, webmail, email_pass, userCampus.campus, luCampus.name as campusName,
                        IF(superusers.id, 1, 0) as su, house, residential_house_id as residential_house, mail_handler, enabled
                FROM user
                    LEFT JOIN groups ON user.group_id = groups.id
                    LEFT JOIN userCampus ON user.id = userCampus.uid
                    LEFT JOIN `luCampus` ON `userCampus`.campus = `luCampus`.id
                    LEFT JOIN superusers ON user.id = superusers.id
                WHERE user.id = '" . $this->db->quote($id) . "' ORDER BY userCampus.defaultCampus DESC";
        return $this->db->getRow($query);
    }

    public function getByUsername($username)
    {
        $query = "SELECT user.id as uid, title, firstname, lastname, username, groups.name AS gname, group_id, year, eportfolio_enabled,`key`, hash,
                        synergy_id, local_email, alt_email, webmail, email_pass, userCampus.campus, IF(superusers.id, 1, 0) as su, house, mail_handler, calendar_view, enabled
                FROM user
                    LEFT JOIN groups ON user.group_id = groups.id
                    LEFT JOIN userCampus ON user.id = userCampus.uid
                    LEFT JOIN superusers ON user.id = superusers.id
                WHERE user.username = " . $this->db->smartQuote($username) . " ORDER BY userCampus.defaultCampus DESC";
        return $this->db->getRow($query);
    }

    public function getSuperusers()
    {
        $query = "SELECT user.id uid, title, firstname, lastname, username, local_email, alt_email
                FROM superusers JOIN user ON superusers.id = user.id";
        return $this->db->getAll($query);
    }

    public function getUserCampus($id)
    {
        $campusList  = array();
        $campusquery = "SELECT campus FROM userCampus WHERE uid = '$id' ORDER BY defaultCampus DESC";
        $this->db->query($campusquery);
        if ($this->db->countRows() > 0) {
            while ($campus = $this->db->getNextArray()) {
                if ($campus['campus']) {
                    $campusList[] = $campus['campus'];
                }
            }
        } else {
            //Place user in campus 1 by default in not assigned
            $campusList[] = 1;
        }
        return $campusList;
    }

    public function getBySynergy($sid)
    {
        $query = "SELECT user.id AS uid, title, firstname, lastname, username, groups.name AS gname, group_id,
                        synergy_id, local_email, alt_email, userCampus.campus, year, eportfolio_enabled, house, enabled
                FROM user
                    LEFT JOIN groups ON user.group_id = groups.id
                    LEFT JOIN userCampus ON user.id = userCampus.uid
                WHERE user.synergy_id = '$sid' ORDER BY userCampus.defaultCampus DESC";
        return $this->db->getRow($query);
    }

    /**
     * Search for a user by synergy ID, restricted to a group or group array
     *
     * @param $sid
     * @param $gid
     *
     * @return array|bool
     */
    public function getBySynergyAndGroup($sid, $gid)
    {
        $query = "SELECT user.id AS uid, title, firstname, lastname, username, groups.name AS gname, group_id,
                        synergy_id, local_email, alt_email, userCampus.campus, year, eportfolio_enabled, house, enabled
                FROM user
                    LEFT JOIN groups ON user.group_id = groups.id
                    LEFT JOIN userCampus ON user.id = userCampus.uid
                WHERE user.synergy_id = '$sid'
                AND ";
        if (is_array($gid)) {
            $query .= "group_id IN (" . implode(", ", $gid) . ")";
        } else {
            $query .= "group_id = '$gid'";
        }
        $query .= " ORDER BY userCampus.defaultCampus DESC";
        return $this->db->getRow($query);
    }

    /**
     * Search the user table for particular user(s)
     *
     * @param        $keyword
     * @param int    $offset
     * @param int    $limit
     * @param null   $count
     * @param string $sort_by
     * @param string $sort_dir
     *
     * @return array|bool
     */
    public function search($keyword, $offset = 0, $limit = 20, &$count = null, $sort_by = 'lastname', $sort_dir = 'ASC')
    {
        // test for exact search, and if so return those results instead
        if (preg_match('#".+"#', $keyword)) {
            // strip the prepended and appended quote characters
            $keyword = substr($keyword, 1, strlen($keyword) - 2);
            return $this->searchExact($keyword, $offset, $limit, $count, $sort_by, $sort_dir);
        }
        // Don't allow LIKE searches on keywords < 3 chars long
        $wherePart = sprintf("( synergy_id = '%s'", $this->db->quote($keyword));
        if (mb_strlen($keyword, 'UTF-8') >= 3) {
            $wherePart .= sprintf(
                " OR CONCAT_WS(' ',title,firstname,lastname) LIKE '%1\$s'
                OR CONCAT_WS(' ',title,lastname) LIKE '%1\$s'
                OR username LIKE '%1\$s'",
                $this->db->quote('%' . $this->db->quoteForLike($keyword) . '%')
            );
        }
        $wherePart .= ')';

        //If parent search policy is disabled, only staff will see parents in search results
        //Guests never see parents
        if( $_SESSION['user']->getRole()->isGuest() ||
            (!$_SESSION['user']->getRole()->isStaff() && Cfg::get('parent_search_enable') != 1)
        ) {
            $wherePart .= " AND `groups`.`group_type` != 'parent' ";
        }

        // retreive results
        /* Allow search on synergy id
         */
        $query = "
            SELECT user.*, groups.name AS group_name, luCampus.name AS campus_name
            FROM `user`
            LEFT JOIN `groups` ON `user`.group_id = `groups`.id
            LEFT JOIN `userCampus` ON `user`.id = `userCampus`.uid AND defaultCampus = 1
            LEFT JOIN `luCampus` ON `userCampus`.campus = `luCampus`.id
            WHERE $wherePart
        ";
        $query .= " ORDER BY `$sort_by` $sort_dir
                        LIMIT $offset, $limit;";
        // retreive count
        $count_query = "SELECT COUNT(*) FROM `user` LEFT JOIN `groups` ON `user`.group_id = `groups`.id WHERE $wherePart";

        $count       = $this->db->getOne($count_query);
        return $this->db->getAll($query);
    }

    public function searchExact(
        $keyword,
        $offset = 0,
        $limit = 20,
        &$count = null,
        $sort_by = 'lastname',
        $sort_dir = 'ASC'
    ) {
        $keyword = $this->db->quote($keyword);

        //If parent search policy is disabled, only staff will see parents in search results
        //Guests never see parents
        if( $_SESSION['user']->getRole()->isGuest() ||
            (!$_SESSION['user']->getRole()->isStaff() && Cfg::get('parent_search_enable') != 1)
        ) {
            $wherePart = " AND `groups`.`group_type` != 'parent' ";
        }

        // retrieve results
        $query = "SELECT user.*, groups.name AS group_name, luCampus.name AS campus_name
                    FROM `user`
                    LEFT JOIN `groups` ON `user`.group_id = `groups`.id
                    LEFT JOIN `userCampus` ON `user`.id = `userCampus`.uid AND defaultCampus = 1
                    LEFT JOIN `luCampus` ON `userCampus`.campus = `luCampus`.id
                    WHERE ( firstname = '$keyword'
                    OR lastname = '$keyword'
                    OR username = '$keyword' )
                    $wherePart
                    ORDER BY `$sort_by` $sort_dir
                    LIMIT $offset, $limit;";
        // retreive count
        $count_query = "SELECT COUNT(*)
                                FROM `user`
                                LEFT JOIN `groups` ON `user`.group_id = `groups`.id
                                WHERE ( firstname = '$keyword'
                                OR lastname = '$keyword'
                                OR username = '$keyword' )
                                $wherePart";
        $count       = $this->db->getOne($count_query);
        return $this->db->getAll($query);
    }

    public function ajaxSearch($keyword, $group = 0, $boardersOnly = false, $limit = false)
    {
        //search for a user based on at least one of the above fields
        //assumes at least one contains something
        $query = "
            SELECT
                user.id AS uid,
                title,
                firstname,
                lastname,
                username,
                groups.name AS gname,
                year,
                local_email,
                alt_email,
                synergy_id,
                luCampus.id as campusId,
                luCampus.name as campusName
            FROM user
            LEFT JOIN groups ON user.group_id = groups.id
            LEFT JOIN userCampus defaultCampus
              ON user.id = defaultCampus.uid
              AND defaultCampus.defaultCampus IS TRUE
            LEFT JOIN luCampus
              ON defaultCampus.campus = luCampus.id
            WHERE
        ";
        if ($keyword != "") {
            if (is_numeric($keyword)) {
                $param = $this->db->quote($keyword);
                $query .= "( synergy_id = $param OR year = $param ) ";
            } else {
                if (preg_match('/([[:alpha:]\'"]+) ([[:alpha:]\'"]+)/', $keyword, $name)) {
                    $firstName = $this->db->quoteForLike($name[1]);
                    $lastName  = $this->db->quoteForLike($name[2]);
                    $query .= "( firstname like '%{$firstName}%' and lastname like '%{$lastName}%' ) ";
                } else {
                    $param = $this->db->quoteForLike($keyword);
                    $query .= "( firstname like '%$param%' or lastname like '%$param%' or username like '%$param%' ) ";
                }
            }
            if (is_array($group)) {
                $query .= "AND group_id IN (" . implode(',', array_map(array($this->db, 'quote'), $group)) . ") ";
            } else {
                if (is_numeric($group) && $group > 0) {
                    $param = $this->db->quote($group);
                    $query .= "AND group_id = '$param' ";
                }
            }
        } else {
            if ($group) {
                $param = $this->db->quote($group);
                $query .= "group_id = '$param' ";
            } else {
                error(2);
            }
        }
        // Only students in boarding house are fetched
        if ($boardersOnly) {
            $query .= "AND IFNULL(residential_house_id, 0) > 0 ";
        }
        $query .= "ORDER BY group_id, year, lastname, firstname";
        if (is_numeric($limit)) {
            $query .= " LIMIT $limit";
        }
        return $this->db->getAll($query);
    }


    /**
     * Sets the user's password.
     *
     * If the Synergetic API is enabled, this also attempts to set their
     * password in Synergetic. If that process fails, then the password will be
     * reverted.
     *
     * @param $userID
     * @param $oldPassword
     * @param $newPassword
     *
     * @return bool Was the password changed?
     */
    public function setPassword($userID, $oldPassword, $newPassword)
    {
        //
        // Set password, and set it back if updating via Synergetic fails.
        $user     = $this->getByID($userID);
        $username = $user['username'];
        if ($this->checkPassword($userID, $oldPassword)
            && $this->setPasswordInternal($userID, $newPassword)
        ) {
            $groupHandler   = new Group();
            $webServicesUrl = Cfg::get('external_web_services_url');
            if ($groupHandler->isParent($userID)
                && Cfg::get('external_enable')
                && Cfg::get('external_type') === 'synergetic'
                && !empty($webServicesUrl)
            ) {
                //
                // Attempt to set with Synergetic.
                // If that is set up and fails, then revert their password.
                $user       = $this->getByID($userID);
                $is_changed = false;
                try {
                    $api        = new External_Synergetic_Gateway(
                        $webServicesUrl,
                        Cfg::get('external_vendor_token_guid'),
                        Cfg::get('external_vendor_password')
                    );
                    $is_changed = $api->changeCommunityLoginPassword($user['synergy_id'], $oldPassword, $newPassword);

                    if (!$is_changed) {
                        //
                        // Should throw a different error message if the remote pwd doesn't get changed.
                        $this->setPasswordInternal($userID, $oldPassword);
                        $this->logUserPasswordChange($username, 'change_pwd_syn_fail', 'warn', $api->getLastError());
                        $this->sendPasswordChangeFailureEmail($userID, true);
                        return false;
                    }

                } catch (SoapFault $ex) {
                    $this->logUserPasswordChange($username, 'change_pwd_syn_broken', 'warn', $ex);
                    $this->sendPasswordChangeFailureEmail($userID, true);
                    return false;
                }
            }
            $this->logUserPasswordChange($username, 'change_pwd', 'info');
            return true;
        }
        $this->logUserPasswordChange($username, 'change_pwd_fail', 'warn');
        $this->sendPasswordChangeFailureEmail($userID);
        return false;
    }


    /**
     * Checks the user's password in the database.
     *
     * @param int    $user_id
     * @param string $password
     *
     * @return bool Does the user ID have this password?
     */
    protected function checkPassword($user_id, $password)
    {
        $esc_user_id  = $this->db->quote($user_id);
        $esc_password = $this->db->quote($password);
        $query        =
            "SELECT `" . $this->_index . "` FROM `" . $this->_table . '` ' .
            "WHERE `" . $this->_index . "` = $esc_user_id AND password = PASSWORD('$esc_password')";
        $this->db->query($query);
        return ($this->db->countRows() > 0);
    }


    /**
     * Sets the user's password in the database.
     *
     * @param int    $userID
     * @param string $newPassword
     *
     * @return bool
     */
    public function setPasswordInternal($userID, $newPassword)
    {
        $newPassword = $this->db->quote($newPassword);
        $query       = "UPDATE `" . $this->_table . "` SET password = PASSWORD('$newPassword'), email_pass = '$newPassword' WHERE `" . $this->_index . "` = $userID";
        $this->db->query($query);
        return $this->db->affectedRows();
    }

    /**
     * Sends a change password confirmation email.
     *
     * @param int $id The user ID to send to.
     *
     * @return bool
     */
    public function sendChangePasswordEmail($id)
    {
        if ($user = $this->getByID($id)) {
            $this->logUserPasswordChange($user['username'], 'email_change_pwd', 'info');
            $hash = substr(md5(uniqid() . time()), 0, 25);
            $this->update(array('id' => $user['id'], 'hash' => $hash));
            $mailHandler = new Mailer('change-password.html', Cfg::get(
                'password_change_email_subject'
            ), $user['alt_email']);
            $from        = Cfg::get('password_change_email_from');
            if ($from) {
                $mailHandler->setFrom($from);
            }
            $mailHandler->setVar('NAME', $user['firstname']);
            $mailHandler->setVar('DATE', date('j F Y', $_SERVER['REQUEST_TIME']));
            $mailHandler->setVar('TIME', date('g:i a', $_SERVER['REQUEST_TIME']));
            return $mailHandler->send();
        }
        return false;
    }

    /**
     * Sends a password change fail message to the Schoolbox admin, if there is
     * one.
     *
     * @param int  $id                    The user ID to send to.
     * @param bool $is_synergetic_failure True if the password failed to change
     *                                    because of something in Synergetic
     *                                    rather than them putting the wrong
     *                                    password in.
     *
     * @return bool
     */
    public function sendPasswordChangeFailureEmail($id, $is_synergetic_failure = false)
    {
        $admin_address = Cfg::get('mail_admin_address');
        if (!empty($admin_address) && ($user = $this->getByID($id))) {
            //
            // Send a different email if the password change failed because the
            // passwords are desynched between Synergetic and Schoolbox.
            // If that happens, then it's not really the user's fault.
            $template = 'change-password-failure.html';
            $subject  = Cfg::get('password_change_failed_email_subject');
            if ($is_synergetic_failure) {
                $template = 'change-password-synergetic-failure.html';
            }
            $mailHandler = new Mailer($template, $subject, $admin_address);
            $mailHandler->setVar('USERNAME', $user['username']);
            $mailHandler->setVar('NAME', $user['firstname'] . ' ' . $user['lastname']);
            $mailHandler->setVar('EMAIL', $user['alt_email']);
            $mailHandler->setVar('IP', $_SERVER['REMOTE_ADDR']);
            $mailHandler->setVar('DATE', date('j F Y', $_SERVER['REQUEST_TIME']));
            $mailHandler->setVar('TIME', date('g:i a', $_SERVER['REQUEST_TIME']));
            return $mailHandler->send();
        }
        return false;
    }


    public function sendPassword($email)
    {
        if (empty($email)) {
            return false;
        }
        $groupHandler = new Group();
        $user_list    = $this->getAllByField('alt_email', $email);
        if (!empty($user_list)) {
            foreach ($user_list as $user) {
                if ($groupHandler->isParent($user['id'])) {
                    $mailHandler = new Mailer(
                        'new-password.html',
                        Cfg::get('title') . ' Password Reminder',
                        $user['alt_email']
                    );
                    $mailHandler->setVar('NAME', $user['firstname']);
                    $mailHandler->setVar('USERNAME', $user['username']);
                    $mailHandler->setVar('PASSWORD', $user['email_pass']);
                    $mailHandler->send();
                    return true;
                }
            }
        }
        return false;
    }

    public function getCampusList()
    {
        $query = "SELECT * FROM luCampus";
        return $this->db->getAll($query);
    }

    public function getGroupList()
    {
        $query = "SELECT * FROM groups";
        return $this->db->getAll($query);
    }

    public function getTitleList()
    {
        $query = "SELECT * FROM luTitle";
        return $this->db->getAll($query);
    }

    public function getUserActivity($uid, $limit = 10)
    {
        $query = "SELECT object, action, time, c.name AS folder, f.description AS file, l.description AS link
                    FROM cmsLog
                    LEFT JOIN category c ON c.id = objectId
                    LEFT JOIN file f ON f.id = objectId
                    LEFT JOIN link l ON l.id = objectId
                    WHERE user = $uid
                    ORDER BY time DESC
                    LIMIT 0,$limit";
        return $this->db->getAll($query);
    }

    public function getUserLogins($username, $limit = 10)
    {
        $query = "SELECT date, ip, login FROM login_log WHERE username like "
            . $this->db->smartQuote($username)
            . " ORDER BY date DESC LIMIT 0,$limit";
        return $this->db->getAll($query);
    }

    public function delete($id, $trans = 0)
    {
        //first transfer ownership of date to another user
        $this->transferOwnership($id, $trans);
        //remove all submission boxes owned by user
        $this->cleanSubmissionBoxes($id);
        //remove all work returned to user
        $this->cleanReturnedWork($id);
        //remove all remaining traces of user
        $this->cleanUserFromSystem($id);
        //remove user from database
        parent::delete($id);
    }

    public function transferOwnership($curr, $new)
    {
        //update all files
        $query = "UPDATE file SET owner = '$new' WHERE owner = '$curr'";
        $this->db->query($query);
        //update all links
        $query = "UPDATE link SET owner = '$new' WHERE owner = '$curr'";
        $this->db->query($query);
        //update all news
        $query = "UPDATE news_items SET item_owner = '$new' WHERE item_owner = '$curr'";
        $this->db->query($query);
        $query = "UPDATE subject_news SET owner = '$new' WHERE owner = '$curr'";
        $this->db->query($query);
        $query = "UPDATE wikiRevision SET userID = '$new' WHERE userID = '$curr'";
        $this->db->query($query);
        //update all forums
        $query = "UPDATE forumPost SET userID = '$new' WHERE userID = '$curr'";
        $this->db->query($query);
        $query = "UPDATE forumThread SET userID = '$new' WHERE userID = '$curr'";
        $this->db->query($query);
        $query = "UPDATE event SET owner = '$new' WHERE owner = '$curr'";
        $this->db->query($query);
    }

    public function cleanSubmissionBoxes($id)
    {
        //first select all users submission boxes
        $query  = "SELECT id FROM submission_box WHERE owner = '$id' AND task_id IS NULL";
        $result = $this->db->query($query);
        //foreach submission box delete all student submissions
        while ($box = $this->db->getNextArray($result)) {
            //select all student submissions
            $query = "SELECT id FROM student_submission WHERE box_id = '" . $box['id'] . "'";
            $this->db->query($query);
            //for each student submission delete from hdd
            while ($studsub = $this->db->getNextArray()) {
                @unlink(Cfg::get('submission_dir') . "/" . $studsub['id'] . ".dat");
            }
            //delete all student submissions from db
            $query = "DELETE FROM student_submission WHERE box_id = '" . $box['id'] . "'";
            $this->db->query($query);
        }
        //delete all submission boxes from db
        $query = "DELETE FROM submission_box WHERE owner = '$id' AND task_id IS NULL";
        $this->db->query($query);
    }

    public function cleanReturnedWork($id)
    {
        $query = "SELECT id FROM submission_return WHERE owner = '$id'";
        $this->db->query($query);
        while ($ret = $this->db->getNextArray()) {
            @unlink(Cfg::get('returns_dir') . "/" . $ret['id'] . ".dat");
        }
        $query = "DELETE FROM submission_return WHERE owner = '$id'";
        $this->db->query($query);
    }

    public function cleanUserFromSystem($id)
    {
        //remove users bookmarks
        $query = "DELETE FROM bookmarks WHERE owner = '$id'";
        $this->db->query($query);
        //superusers
        $query = "DELETE FROM superusers WHERE id = '$id'";
        $this->db->query($query);
        //user access
        $query = "DELETE FROM userAccess WHERE uid = '$id'";
        $this->db->query($query);
        //delete personal calendar events
        $query = "DELETE FROM event WHERE access = '1' AND owner = '$id'";
        $this->db->query($query);
        $query = "DELETE FROM newsUser WHERE userID = '$id'";
        $this->db->query($query);
        //delete log entries
        $query = "DELETE FROM homepage_log WHERE user = '$id'";
        $this->db->query($query);
        $query = "DELETE FROM submission_log WHERE user = '$id'";
        $this->db->query($query);
        $query = "DELETE FROM submission_log WHERE user = '$id'";
        $this->db->query($query);
//		/* None of these tables are still used, and all will be deleted */
//		$query = "DELETE eportfolio_blog, eportfolio_blogComment FROM eportfolio_blog, eportfolio_blogComment WHERE eportfolio_blogComment.bid = eportfolio_blog.id AND eportfolio_blog.owner = '$id'";
//		$this->db->query($query);
//		$query = "DELETE FROM eportfolio_config WHERE uid = '$id'";
//		$this->db->query($query);
//		$query = "DELETE FROM eportfolio_link WHERE owner = '$id'";
//		$this->db->query($query);
        // Delete eportfolio pages properly
        $mapper = Eportfolio\PageMapper::getInstance();
        $mapper->delete($mapper->findByColumn('uid', $id));
        $this->db->delete('userCampus', 'uid', $id);
    }

    /**
     * Update an existing user's details
     *
     * @param array $user
     * @return bool
     */
    public function update($user)
    {
        /* Check if the username already exists before updating. If it does
              * exist already, prevent existence of duplicate usernames
              */
        if ($dupeUser = $this->getByUsername($user['username'])) {
            if ($dupeUser['uid'] != $user['id']) {
                return false;
            }
        }
        $campusArray   = $user['campus'];
        $defaultCampus = $user['defaultCampus'];
        unset($user['campus']);
        unset($user['defaultCampus']);
        /* Check if $user['su'] exists and not equal to null
              */
        if (isset($user['su'])) {
            $this->db->delete('superusers', 'id', $user['id']);
            if ($user['su']) {
                $this->db->insert('superusers', array('id' => $user['id']));
            }
            unset($user['su']);
        } /* Check if $user['su'] exists and is null
         */
        else {
            if (array_key_exists('su', $user)) {
                unset($user['su']);
            }
        }
        if (isset($user['password'])) {
            $this->setPasswordInternal($user['id'], $user['password']);
            unset($user['password']);
        }
        parent::update($user);
        if (is_array($campusArray)) {
            $this->db->delete('userCampus', 'uid', $user['id']);
            foreach ($campusArray as $campus) {
                $insertCampus = array(
                    'uid'           => $user['id'],
                    'campus'        => $campus,
                    'defaultCampus' => $campus == $defaultCampus ? 1 : 0
                );
                $this->db->insert('userCampus', $insertCampus);
            }
        } else {
            if (is_numeric($campusArray) && $campusArray > 0) {
                /* Note that this can insert multiple campus if the update only specified a single value like in a bulk load */
                $campusquery = "INSERT IGNORE INTO userCampus (uid, campus) VALUES ('{$user['id']}', '$campusArray')";
                $this->db->query($campusquery);
            }
        }
        // Filthily notify the binder that user was updated
        $this->getUserNotifier()->manualNotifyUpdated($user['id'], $_SESSION['user']);
        return true;
    }

    /**
     * Create a new user record
     *
     * @param array $user
     *
     * @return int|bool ID of new user, or false if failed
     */
    public function create($user)
    {
        /* Check if the username already exists
              */
        if (is_array($this->getByUsername($user['username']))) {
            return false;
        }
        $campusArray   = $user['campus'];
        $defaultCampus = $user['defaultCampus'];
        $su            = $user['su'];
        $password      = $user['password'];
        unset($user['campus']);
        unset($user['defaultCampus']);
        unset($user['su']);
        unset($user['password']);
        $user['key'] = substr(md5(uniqid() . time()), 0, 25);
        if (is_array($campusArray) && count($campusArray)) {
            $id = parent::create($user);
            if ($password) {
                $this->setPasswordInternal($id, $password);
            }
            if (is_array($campusArray) && $id) {
                foreach ($campusArray as $campus) {
                    $insertCampus = array(
                        'uid'           => $id,
                        'campus'        => $campus,
                        'defaultCampus' => $campus == $defaultCampus ? 1 : 0
                    );
                    $this->db->insert('userCampus', $insertCampus);
                }
            } else {
                if ($id && $campusArray) {
                    $campusquery = "INSERT INTO userCampus (uid, campus) VALUES ('$id', '$campusArray')";
                    $this->db->query($campusquery);
                }
            }
            /* If ticked, add the new user into the Superusers table as well.
                      */
            if ($su) {
                $this->db->insert('superusers', array('id' => $id));
            }
            // Filthily notify the binder that there's a new user
            $this->getUserNotifier()->manualNotifyCreated($id, $_SESSION['user']);
            return $id;
        }
        return false;
    }


    /**
     * Gets all in the system who are in a particular groupID, restricted by index and limit
     *
     * @param      $groupID
     * @param bool $hasSynergy
     * @param int  $index
     * @param int  $limit
     * @return array
     */
    public function getSelectionByGroup($groupID, $hasSynergy = false, $index = 0, $limit = 0)
    {
        $query = "SELECT *
                  FROM {$this->_table}
                  WHERE group_id";
        if (is_array($groupID)) {
            $query .= " IN " . '(' . implode(',', $groupID) . ')';
        } else {
            $query .= " = '$groupID'";
        }
        /* Optionally exclude those without synergy ids
              */
        if ($hasSynergy) {
            $query .= " AND synergy_id IS NOT NULL
                        AND synergy_id > 0 ";
        }
        $query .= " ORDER BY lastname ASC, firstname ASC";
        if ($limit) {
            $query .= " LIMIT $index, $limit";
        }
        return $this->db->getAll($query);
    }

    /**
     * Gets all in the system who are in a particular groupID
     *
     * @param int $groupID
     * @param bool $hasSynergy If true (default: false), only returns users with synergy IDs
     * @return array
     */
    public function getByGroup($groupID, $hasSynergy = false)
    {
        $query = "SELECT *
                  FROM {$this->_table}
                  WHERE group_id";
        if (is_array($groupID)) {
            $query .= " IN " . '(' . implode(',', $groupID) . ')';
        } else {
            $query .= " = '$groupID'";
        }
        /* Optionally exclude those without synergy ids
              */
        if ($hasSynergy) {
            $query .= " AND synergy_id IS NOT NULL
                        AND synergy_id > 0 ";
        }
        return $this->db->getAll($query);
    }

    /**
     * Format a name from a user object
     *
     * @param  array $userInfo
     * @param string $format
     * @param bool   $useNonBreakingSpace
     *
     * @return mixed|null|string
     */
    public function formatName($userInfo, $format = null, $useNonBreakingSpace = false)
    {
        // Check for custom format
        if (empty($format)) {
            $format = Cfg::get('name_format');
        }
        /* Replace tags */
        preg_match_all('|{\w+}|', $format, $tags);
        if (empty($tags) || empty($userInfo)) {
            return 'Unknown User';
        }
        foreach ($tags[0] as $tag) {
            $key = trim($tag, '{}');
            if (is_string($userInfo[$key])) {
                $value = $userInfo[$key];
            } else {
                $value = '';
            }
            $format = str_replace($tag, $value, $format);
        }
        if ($format = trim($format)) {
            if ($useNonBreakingSpace) {
                $format = preg_replace("#[\s]#", '&nbsp;', $format);
            }
            return $format;
        } elseif ($useNonBreakingSpace) {
            return 'Unknown&nbsp;User';
        } else {
            return 'Unknown User';
        }
    }

    /**
     * Logs user-related activity in the database.
     *
     * @param string $user    The user's username.
     * @param string $event   A simple code to describe the error, 20 chars max.
     * @param string $level   The log level: trace, debug, info, warning, error
     * @param string $message Optional extra information, 250 chars max.
     *
     * @return bool
     */
    public function logUserPasswordChange($user, $event, $level, $message = '')
    {
        //
        // I will NOT log passwords here!
        // when user changes password (change_pwd, info)
        // when user tries to change password but enters wrong password (change_pwd_fail, warn)
        // when user tries but is foild by Synergetic (change_pwd_syn_fail, warn)
        // when an admin or someone changes other user's password (change_other_password, info)
        return $this->db->insert(
            'user_activity_log',
            array(
                'username' => $user,
                'date'     => 'NOW()',
                'ip'       => $_SERVER['REMOTE_ADDR'],
                'event'    => $event,
                'level'    => $level,
                'message'  => $message,
            )
        );
    }
}
