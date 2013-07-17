<?php

class Leave_Request extends Model {

    /* Host types and their presentation labels.
      */
    private $hostTypeMap = array(
        '1' => 'Mother',
        '2' => 'Father',
        '3' => 'Guardian',
        '4' => 'Self',
        '5' => 'Other'
    );

    /* Leave Requests confirmation types and their presentation labels
      */
    private $confirmedByMap = array(
        '1' => 'Email',
        '2' => 'Fax',
        '3' => 'Phone',
        '4' => 'Person',
        '5' => 'Text',
    );

    const STATUS_PENDING = 1;
    const STATUS_APPROVED = 2;
    const STATUS_REJECTED = 3;
    const STATUS_DELETED = 4;

    /* These really shouldn't be public - and now they aren't, rejoice!
      */
    protected $id;
    protected $userId;
    protected $user;
    protected $start;
    protected $finish;
    protected $hostType;
    protected $hostName;
    protected $hostPhone;
    protected $userPhone;
    protected $description;
    protected $created;
    protected $status;
    protected $authorisationDatetime;
    protected $authorisationUserId;
    protected $authorisationUser;
    protected $notes;
    protected $confirmedBy;

    /**
     * @param $created
     */
    public function setCreated($created) {
        $this->created = $created;
    }

    /**
     * @param bool $datetime
     *
     * @return Alaress_Date_Format
     */
    public function getCreated($datetime = false) {
        if ($datetime) {
            return new Alaress_Date_Format($this->created, 'y-m-d');
        } else {
            return $this->created;
        }
    }

    /**
     * Set description
     *
     * @param $description
     */
    public function setDescription($description) {
        $this->description = $description;
    }

    /**
     * Get description
     *
     * @return mixed
     */
    public function getDescription() {
        return $this->description;
    }

    /**
     * Set finish
     *
     * @param $finish
     */
    public function setFinish($finish) {
        $this->finish = $finish;
    }

    /**
     * Get finish
     *
     * @return Alaress_Date_Format
     */
    public function getFinish() {
        return new Alaress_Date_Format($this->finish, 'y-m-d h:i:s');
    }

    /**
     * Set host name
     *
     * @param $hostName
     */
    public function setHostName($hostName) {
        $this->hostName = $hostName;
    }

    /**
     * Get host name
     *
     * @return mixed
     */
    public function getHostName() {
        return $this->hostName;
    }

    /**
     * Set host phone
     *
     * @param $hostPhone
     */
    public function setHostPhone($hostPhone) {
        $this->hostPhone = $hostPhone;
    }

    /**
     * Get host phone
     *
     * @return mixed
     */
    public function getHostPhone() {
        return $this->hostPhone;
    }

    /**
     * Set host type
     *
     * @param $hostType
     */
    public function setHostType($hostType) {

        $this->hostType = $hostType;
    }

    /**
     * Get host type
     *
     * @param bool $string
     *
     * @return string
     */
    public function getHostType($string = false) {
        if ($string) {
            return $this->getHostTypeMapIndex($this->hostType);
        } else {
            return $this->hostType;
        }
    }

    /**
     * Set id
     *
     * @param $id
     */
    public function setId($id) {
        $this->id = $id;
    }

    /**
     * Get id
     *
     * @return mixed
     */
    public function getId() {
        return $this->id;
    }

    /**
     * Set start
     *
     * @param $start
     */
    public function setStart($start) {
        $this->start = $start;
    }

    /**
     * Get start
     *
     * @return Alaress_Date_Format
     */
    public function getStart() {
        return new Alaress_Date_Format($this->start, 'y-m-d h:i:s');
    }

    /**
     * Set status
     *
     * @param $status
     */
    public function setStatus($status) {
        $this->status = $status;
    }

    /**
     * Determine if a leave request is pending
     *
     * @return bool
     */
    public function isPending() {
        if ($this->status == Leave_Request::STATUS_PENDING) {
            return true;
        }

        return false;
    }

    /**
     * Determine if a leave request is approved
     *
     * @return bool
     */
    public function isApproved() {
        if ($this->status == Leave_Request::STATUS_APPROVED) {
            return true;
        }

        return false;
    }

    /**
     * Determine if a leave request is rejected
     *
     * @return bool
     */
    public function isRejected() {
        if ($this->status == Leave_Request::STATUS_REJECTED) {
            return true;
        }

        return false;
    }


    /**
     * Determine if a leave request is deleted
     *
     * @return bool
     */
    public function isDeleted() {
        if ($this->status == Leave_Request::STATUS_DELETED) {
            return true;
        }

        return false;
    }

    /**
     * Get status
     *
     * @param bool $string
     *
     * @return string
     */
    public function getStatus($string = false) {
        if ($string) {
            if ($this->isPending()) {
                return 'Pending';
            } elseif ($this->isApproved()) {
                return 'Approved';
            } elseif ($this->isRejected()) {
                return 'Rejected';
            } elseif ($this->isDeleted()) {
                return 'Deleted';
            }
        } else {
            return $this->status;
        }
    }

    /**
     * Set user id
     *
     * @param $userId
     */
    public function setUserId($userId) {
        $this->userId = $userId;
    }

    /**
     * Get user id
     *
     * @return mixed
     */
    public function getUserId() {
        return $this->userId;
    }

    /**
     * Set user phone
     *
     * @param $userPhone
     */
    public function setUserPhone($userPhone) {
        $this->userPhone = $userPhone;
    }

    /**
     * Get user phone
     *
     * @return mixed
     */
    public function getUserPhone() {
        return $this->userPhone;
    }

    /**
     * Set user
     *
     * @param \Schoolbox\User $user
     *
     * @return void
     */
    public function setUser($user) {
        $this->user = $user;
    }

    /**
     * Get user
     *
     * @return \Schoolbox\User
     */
    public function getUser() {
        return $this->user;
    }

    /**
     * Set authorisation date
     *
     * @param $authorisationDatetime
     */
    public function setAuthorisationDatetime($authorisationDatetime) {
        $this->authorisationDatetime = $authorisationDatetime;
    }

    /**
     * Get authorisation date
     *
     * @param bool $datetime
     *
     * @return Alaress_Date_Format
     */
    public function getAuthorisationDatetime($datetime = false) {
        if ($datetime) {
            return new Alaress_Date_Format($this->authorisationDatetime, 'y-m-d');
        } else {
            return $this->authorisationDatetime;
        }
    }

    /**
     * Set authorisation user
     *
     * @param Schoolbox\User $authorisationUser
     */
    public function setAuthorisationUser($authorisationUser) {
        $this->authorisationUser = $authorisationUser;
    }

    /**
     * Get authorisation user
     *
     * @return Schoolbox\User
     */
    public function getAuthorisationUser() {
        return $this->authorisationUser;
    }

    /**
     * Set authorisation user id
     *
     * @param $authorisationUserId
     */
    public function setAuthorisationUserId($authorisationUserId) {
        $this->authorisationUserId = $authorisationUserId;
    }

    /**
     * Get authorisation user id
     * @return mixed
     */
    public function getAuthorisationUserId() {
        return $this->authorisationUserId;
    }

    /**
     * Get duration
     *
     * @return float
     */
    public function getDuration() {
        $duration = floor(
            ($this->getFinish()->getUnixTimestamp() - $this->getStart()->getUnixTimestamp()) / (60 * 60 * 24)
        );

        return $duration;
    }

    /**
     * Checks to see whether a particular date range falls within the date range of the request
     *
     * @param Alaress_Date_Format $datetimeStart
     * @param Alaress_Date_Format $datetimeFinish
     *
     * @return Boolean
     */
    public function getOccupancy($datetimeStart, $datetimeFinish) {
        if ($this->getStart()->getUnixTimestamp() < $datetimeFinish->getUnixTimestamp() && $this->getFinish(
        )->getUnixTimestamp() > $datetimeStart->getUnixTimestamp()
        ) {
            return true;
        }

        return false;
    }

    /**
     * Set notes
     *
     * @param $notes
     */
    public function setNotes($notes) {
        $this->notes = $notes;
    }

    /**
     * Get notes
     *
     * @return mixed
     */
    public function getNotes() {
        return $this->notes;
    }

    /**
     * Get host type map array
     *
     * @return array
     */
    public function getHostTypeMap() {
        return $this->hostTypeMap;
    }

    /**
     * Get host type label for a host type id
     *
     * @param $index
     * @return mixed
     */
    public function getHostTypeMapIndex($index) {
        return $this->hostTypeMap[$index];
    }

    /**
     * Get confirmed by
     *
     * @return mixed
     */
    public function getConfirmedBy() {
        return $this->confirmedBy;
    }

    /**
     * Set confirmed by
     *
     * @param $confirmedBy
     */
    public function setConfirmedBy($confirmedBy) {
        $this->confirmedBy = $confirmedBy;
    }

    /**
     * Get confirmed by map
     *
     * @return mixed
     */
    public function getConfirmedByMap() {
        return $this->confirmedByMap;
    }

    /**
     * Get confirmed by label for a type id
     *
     * @param $index
     * @return array
     */
    public function getConfirmedByMapIndex($index) {
        return $this->confirmedByMap[$index];
    }
}
