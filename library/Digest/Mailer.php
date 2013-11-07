<?php
/**
 * Helper class for sending digest emails.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

use Schoolbox\Role;

class Digest_Mailer {

    /**
     * The list of groups to send the digest email to.
     *
     * @var Role[]
     */
    protected $role_list;

    /**
     * The from time for events that are sent in the digest.
     *
     * @var DateTime
     */
    protected $from_time;

    /**
     * The to time for events that are sent in the digest.
     *
     * @var DateTime
     */
    protected $to_time;

    /**
     * @var bool
     */
    protected $debug;

    /**
     * Sends digest emails based on this object's group list and time window
     * (from time and to time).
     */
    public function send() {
        $digest = new Digest_News(
            $this->getFromTime()->getTimestamp(),
            $this->getToTime()->getTimestamp(),
            $this->getDebug()
        );
        foreach ($this->getRoleList() as $role) {
            /** @var Role $role */
            printf("[%s] Starting daily news digest for group \"%s\"\n", strftime('%F %T'), $role->getName());
            $digest->compileUserList($role);
            $result = $digest->send();
            printf("[%s] Completed daily news digest for group \"%s\": ", strftime('%F %T'), $role->getName());
            printf("%d sendable, %d missing emails, %d failures\n", $result['total'], $result['missing'], $result['failures']);
        }
    }

    /**
     * @param  bool  $debug
     * @return $this
     */
    public function setDebug($debug) {
        $this->debug = $debug;
        return $this;
    }

    /**
     * @return bool
     */
    public function getDebug() {
        return $this->debug;
    }

    /**
     * @param  DateTime $from_time
     * @return $this
     */
    public function setFromTime($from_time) {
        $this->from_time = $from_time;
        return $this;
    }

    /**
     * @return \DateTime
     */
    public function getFromTime() {
        return $this->from_time;
    }

    /**
     * @param  Role[] $group_list
     * @return $this
     */
    public function setRoleList($group_list) {
        $this->role_list = $group_list;
        return $this;
    }

    /**
     * @return Role[]
     */
    public function getRoleList() {
        return $this->role_list;
    }

    /**
     * @param  DateTime $to_time
     * @return $this
     */
    public function setToTime($to_time) {
        $this->to_time = $to_time;
        return $this;
    }

    /**
     * @return DateTime
     */
    public function getToTime() {
        return $this->to_time;
    }

}
