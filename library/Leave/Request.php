<?php

class Leave_Request
{

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
    protected $user;
    protected $author;
    protected $start;
    protected $finish;
    protected $hostType;
    protected $hostName;
    protected $hostPhone;
    protected $hostMobile;
    protected $hostAddress;
    protected $collectorName;
    protected $returnerName;
    protected $reason;
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
     * The type of travel
     *
     * @var Leave_Request_TravelType
     */
    protected $travelType;

    /**
     * Get duration
     *
     * @return float
     */
    public function getDuration()
    {
        $duration = floor(
            ($this->getFinish()->getUnixTimeStamp() - $this->getStart()->getUnixTimeStamp()) / (60 * 60 * 24)
        );

        return $duration;
    }

    /**
     * Checks to see whether a particular date range falls within the date range of the request
     *
     * @param Alaress_Date_Format $datetimeStart
     * @param Alaress_Date_Format $datetimeFinish
     *
     * @return bool
     */
    public function getOccupancy($datetimeStart, $datetimeFinish)
    {
        if (null === $this->getStart() || $this->getStart()->isNull()) {
            return false;
        }
        if (null === $this->getFinish() || $this->getFinish()->isNull()) {
            return false;
        }
        if (
            $this->getStart()->getUnixTimeStamp() < $datetimeFinish->getUnixTimeStamp()
            && $this->getFinish()->getUnixTimeStamp() > $datetimeStart->getUnixTimeStamp()
        ) {
            return true;
        }

        return false;
    }

    /* Getters/Setters
     */

    /**
     * @param string $created
     */
    public function setCreated($created)
    {
        $this->created = $created;
    }

    /**
     * @param bool $datetime
     *
     * @return Alaress_Date_Format
     */
    public function getCreated($datetime = false)
    {
        if ($datetime) {
            return new Alaress_Date_Format($this->created, Alaress_Date_Format::DATABASE_FORMAT);
        } else {
            return $this->created;
        }
    }

    /**
     * Set description
     *
     * @param string $description
     */
    public function setDescription($description)
    {
        $this->description = $description;
    }

    /**
     * Get description
     *
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * Set finish
     *
     * @param Alaress_Date_Format $finish
     */
    public function setFinish($finish)
    {
        $this->finish = $finish;
    }

    /**
     * Get finish
     *
     * @return Alaress_Date_Format
     */
    public function getFinish()
    {
        return $this->finish;
    }

    /**
     * Set host name
     *
     * @param string $hostName
     */
    public function setHostName($hostName)
    {
        $this->hostName = $hostName;
    }

    /**
     * Get host name
     *
     * @return string
     */
    public function getHostName()
    {
        return $this->hostName;
    }

    /**
     * Set host phone
     *
     * @param string $hostPhone
     */
    public function setHostPhone($hostPhone)
    {
        $this->hostPhone = $hostPhone;
    }

    /**
     * Get host phone
     *
     * @return string
     */
    public function getHostPhone()
    {
        return $this->hostPhone;
    }

    /**
     * Set host type
     *
     * @param Leave\HostType $hostType
     */
    public function setHostType($hostType)
    {

        $this->hostType = $hostType;
    }

    /**
     * Get host type
     *
     * @return \Leave\HostType
     */
    public function getHostType()
    {
        return $this->hostType;
    }

    /**
     * Set id
     *
     * @param int $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * Get id
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set start
     *
     * @param Alaress_Date_Format $start
     */
    public function setStart($start)
    {
        $this->start = $start;
    }

    /**
     * Get start
     *
     * @return Alaress_Date_Format
     */
    public function getStart()
    {
        return $this->start;
    }

    /**
     * Set status
     *
     * @param int $status
     */
    public function setStatus($status)
    {
        $this->status = $status;
    }

    /**
     * Determine if a leave request is pending
     *
     * @return bool
     */
    public function isPending()
    {
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
    public function isApproved()
    {
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
    public function isRejected()
    {
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
    public function isDeleted()
    {
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
    public function getStatus($string = false)
    {
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
            return '';
        } else {
            return $this->status;
        }
    }

    /**
     * Get user id
     *
     * @return mixed
     */
    public function getUserId()
    {
        return $this->getUser()->getId();
    }

    /**
     * Set user phone
     *
     * @param string $userPhone
     */
    public function setUserPhone($userPhone)
    {
        $this->userPhone = $userPhone;
    }

    /**
     * Get user phone
     *
     * @return string
     */
    public function getUserPhone()
    {
        return $this->userPhone;
    }

    /**
     * Set user
     *
     * @param int|\Schoolbox\User $user
     *
     * @return void
     */
    public function setUser($user)
    {
        $this->user = $user;
    }

    /**
     * Get user
     *
     * @return int|\Schoolbox\User
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * Set authorisation date
     *
     * @param string $authorisationDatetime
     */
    public function setAuthorisationDatetime($authorisationDatetime)
    {
        $this->authorisationDatetime = $authorisationDatetime;
    }

    /**
     * Get authorisation date
     *
     * @param bool $datetime
     *
     * @return Alaress_Date_Format
     */
    public function getAuthorisationDatetime($datetime = false)
    {
        if ($datetime) {
            return new Alaress_Date_Format($this->authorisationDatetime, Alaress_Date_Format::DATABASE_FORMAT);
        } else {
            return $this->authorisationDatetime;
        }
    }

    /**
     * Set authorisation user
     *
     * @param Schoolbox\User $authorisationUser
     */
    public function setAuthorisationUser($authorisationUser)
    {
        $this->authorisationUser = $authorisationUser;
    }

    /**
     * Get authorisation user
     *
     * @return Schoolbox\User
     */
    public function getAuthorisationUser()
    {
        return $this->authorisationUser;
    }

    /**
     * Get authorisation user id
     *
     * @return mixed
     */
    public function getAuthorisationUserId()
    {
        return $this->getAuthorisationUser()->getId();
    }

    /**
     * Set notes
     *
     * @param string $notes
     */
    public function setNotes($notes)
    {
        $this->notes = $notes;
    }

    /**
     * Get notes
     *
     * @return string
     */
    public function getNotes()
    {
        return $this->notes;
    }

    /**
     * Get confirmed by
     *
     * @return int
     */
    public function getConfirmedBy()
    {
        return $this->confirmedBy;
    }

    /**
     * Set confirmed by
     *
     * @param int $confirmedBy
     */
    public function setConfirmedBy($confirmedBy)
    {
        $this->confirmedBy = $confirmedBy;
    }

    /**
     * Get confirmed by map
     *
     * @return mixed
     */
    public function getConfirmedByMap()
    {
        return $this->confirmedByMap;
    }

    /**
     * Get confirmed by label for a type id
     *
     * @param int $index
     *
     * @return string|null
     */
    public function getConfirmedByMapIndex($index)
    {
        return $this->confirmedByMap[$index] ?? null;
    }

    /**
     * @param  \Leave_Request_TravelType $travelType
     *
     * @return $this
     */
    public function setTravelType($travelType)
    {
        $this->travelType = $travelType;
        return $this;
    }

    /**
     * @return \Leave_Request_TravelType
     */
    public function getTravelType()
    {
        return $this->travelType;
    }

    /**
     * Set author id
     *
     * @param mixed $authorId
     */
    public function setAuthor($authorId)
    {
        $this->author = $authorId;
    }

    /**
     * Get author id
     *
     * @return mixed
     */
    public function getAuthor()
    {
        return $this->author;
    }

    /**
     * Set collector name
     *
     * @param mixed $collectorName
     */
    public function setCollectorName($collectorName)
    {
        $this->collectorName = $collectorName;
    }

    /**
     * Get collector name
     *
     * @return mixed
     */
    public function getCollectorName()
    {
        return $this->collectorName;
    }

    /**
     * Set host address
     *
     * @param mixed $hostAddress
     */
    public function setHostAddress($hostAddress)
    {
        $this->hostAddress = $hostAddress;
    }

    /**
     * Get host address
     *
     * @return mixed
     */
    public function getHostAddress()
    {
        return $this->hostAddress;
    }

    /**
     * Set host mobile
     *
     * @param mixed $hostMobile
     */
    public function setHostMobile($hostMobile)
    {
        $this->hostMobile = $hostMobile;
    }

    /**
     * Get host mobile
     *
     * @return mixed
     */
    public function getHostMobile()
    {
        return $this->hostMobile;
    }

    /**
     * Set reason
     *
     * @param mixed $reason
     */
    public function setReason($reason)
    {
        $this->reason = $reason;
    }

    /**
     * Get reason
     *
     * @return mixed
     */
    public function getReason()
    {
        return $this->reason;
    }

    /**
     * Set returner name
     *
     * @param mixed $returnerName
     */
    public function setReturnerName($returnerName)
    {
        $this->returnerName = $returnerName;
    }

    /**
     * Get returner name
     *
     * @return mixed
     */
    public function getReturnerName()
    {
        return $this->returnerName;
    }
}
