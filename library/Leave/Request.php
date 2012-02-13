<?php

class Leave_Request extends Model {
	const HOST_TYPE_PARENT = 1;
	const HOST_TYPE_PEER_PARENT = 2;
	const HOST_TYPE_OTHER = 3;
	const STATUS_PENDING = 1;
	const STATUS_APPROVED = 2;
	const STATUS_REJECTED = 3;

	/* These really shouldn't be public.
	 */
	public $id;
	public $userId;
	public $user;
	public $start;
	public $finish;
	public $hostType;
	public $hostName;
	public $hostPhone;
	public $userPhone;
	public $description;
	public $created;
	public $status;
	public $authorisationDatetime;
	public $authorisationUserId;
	public $authorisationUser;
	public $notes;

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
		}
		else {
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
	 * @param bool $datetime
	 *
	 * @return Alaress_Date_Format
	 */
	public function getFinish($datetime = false) {
		if ($datetime) {
			return new Alaress_Date_Format($this->finish, 'y-m-d');
		}
		else {
			return $this->finish;
		}
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
			if ($this->hostType == self::HOST_TYPE_PARENT) {
				return 'Parent';
			}
			elseif ($this->hostType == self::HOST_TYPE_PEER_PARENT) {
				return 'Peer Parent';
			}
		}
		else {
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
	 * @param bool $datetime
	 *
	 * @return Alaress_Date_Format
	 */
	public function getStart($datetime = false) {
		if ($datetime) {
			return new Alaress_Date_Format($this->start, 'y-m-d');
		}
		else {
			return $this->start;
		}
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
	 * Get status
	 *
	 * @param bool $string
	 *
	 * @return string
	 */
	public function getStatus($string = false) {
		if ($string) {
			if ($this->status == self::STATUS_PENDING) {
				return 'Pending';
			}
			elseif ($this->status == self::STATUS_APPROVED) {
				return 'Approved';
			}
			elseif ($this->status == self::STATUS_REJECTED) {
				return 'Rejected';
			}
		}
		else {
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
	 * @param User $user
	 *
	 * @return void
	 */
	public function setUser($user) {
		$this->user = $user;
	}

	/**
	 * Get user
	 *
	 * @return User
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
		}
		else {
			return $this->authorisationDatetime;
		}
	}

	/**
	 * Set authorisation user
	 *
	 * @param $authorisationUser
	 */
	public function setAuthorisationUser($authorisationUser) {
		$this->authorisationUser = $authorisationUser;
	}

	/**
	 * Get authorisation user
	 *
	 * @return mixed
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
		$duration = floor(($this->getFinish(true)->getUnixTimestamp() - $this->getStart(true)->getUnixTimestamp()) / (60 * 60 * 24));

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
		if ($this->getStart(true)->getUnixTimestamp() < $datetimeFinish->getUnixTimestamp() && $this->getFinish(true)->getUnixTimestamp() > $datetimeStart->getUnixTimestamp()) {
			return true;
		}
		else {
			return false;
		}
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
}
