<?php
 
class Leave_Request extends Model {

    const HOST_TYPE_PARENT = 1;

    const HOST_TYPE_PEER_PARENT = 2;

    const STATUS_PENDING = 1;

    const STATUS_APPROVED = 2;

    const STATUS_REJECTED = 3;

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

    public function setCreated($created)
    {
        $this->created = $created;
    }

    public function getCreated($datetime = false)
    {
        if ( $datetime ) {
            return new Alaress_Date_Format($this->created, 'y-m-d');
        }
        else {
            return $this->created;
        }
    }

    public function setDescription($description)
    {
        $this->description = $description;
    }

    public function getDescription()
    {
        return $this->description;
    }

    public function setFinish($finish)
    {
        $this->finish = $finish;
    }

    public function getFinish($datetime = false)
    {
        if ( $datetime ) {
            return new Alaress_Date_Format($this->finish, 'y-m-d');
        }
        else {
            return $this->finish;
        }
    }

    public function setHostName($hostName)
    {
        $this->hostName = $hostName;
    }

    public function getHostName()
    {
        return $this->hostName;
    }

    public function setHostPhone($hostPhone)
    {
        $this->hostPhone = $hostPhone;
    }

    public function getHostPhone()
    {
        return $this->hostPhone;
    }

    public function setHostType($hostType)
    {
        $this->hostType = $hostType;
    }

    public function getHostType($string = false)
    {
        if ( $string ) {
            if ( $this->hostType == self::HOST_TYPE_PARENT ) {
                return 'Parent';
            }
            elseif ( $this->hostType == self::HOST_TYPE_PEER_PARENT ) {
                return 'Peer Parent';
            }
        }
        else {
            return $this->hostType;
        }
    }

    public function setId($id)
    {
        $this->id = $id;
    }

    public function getId()
    {
        return $this->id;
    }

    public function setStart($start)
    {
        $this->start = $start;
    }

    public function getStart($datetime = false)
    {
        if ( $datetime ) {
            return new Alaress_Date_Format($this->start, 'y-m-d');
        }
        else {
            return $this->start;
        }
    }

    public function setStatus($status)
    {
        $this->status = $status;
    }

    public function getStatus($string = false)
    {
        if ( $string ) {
            if ( $this->status == self::STATUS_PENDING ) {
                return 'Pending';
            }
            elseif ( $this->status == self::STATUS_APPROVED ) {
                return 'Approved';
            }
            elseif ( $this->status == self::STATUS_REJECTED ) {
                return 'Rejected';
            }
        }
        else {
            return $this->status;
        }
    }

    public function setUserId($userId)
    {
        $this->userId = $userId;
    }

    public function getUserId()
    {
        return $this->userId;
    }

    public function setUserPhone($userPhone)
    {
        $this->userPhone = $userPhone;
    }

    public function getUserPhone()
    {
        return $this->userPhone;
    }

    /**
     * @param User $user
     * @return void
     */
    public function setUser($user)
    {
        $this->user = $user;
    }

    /**
     * @return User
     */
    public function getUser()
    {
        return $this->user;
    }

    public function setAuthorisationDatetime($authorisationDatetime)
    {
        $this->authorisationDatetime = $authorisationDatetime;
    }

    public function getAuthorisationDatetime($datetime = false)
    {
        if ( $datetime ) {
            return new Alaress_Date_Format($this->authorisationDatetime, 'y-m-d');
        }
        else {
            return $this->authorisationDatetime;
        }
    }

    public function setAuthorisationUser($authorisationUser)
    {
        $this->authorisationUser = $authorisationUser;
    }

    public function getAuthorisationUser()
    {
        return $this->authorisationUser;
    }

    public function setAuthorisationUserId($authorisationUserId)
    {
        $this->authorisationUserId = $authorisationUserId;
    }

    public function getAuthorisationUserId()
    {
        return $this->authorisationUserId;
    }

    public function getDuration () {
        $duration = floor( ( $this->getFinish(true)->getUnixTimestamp() - $this->getStart(true)->getUnixTimestamp() ) / ( 60 * 60 * 24) );

        return $duration;
    }
}
