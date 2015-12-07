<?php
/**
 * Description of the file and what its purpose is
 *
 * @author      James Leckie <james.leckie@alaress.com.au>
 * @copyright   2013 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 */
namespace Schoolbox;

class Role implements ValueLabelInterface
{

    /** @var int */
    private $id;
    /** @var string */
    private $name;
    /** @var string */
    private $type; //Type of user group, guest, student, parent, staff
    /** @var bool */
    private $canChangePassword; //Users can change passwords (bool)

    /**
     * What's the digest schedule for this group?
     * One of self::DIGEST_NEVER, self::DIGEST_DAILY, self::DIGEST_WEEKLY, or a cron schedule
     *
     * @var string
     */
    protected $digestSchedule;

    const GUEST = 'guest';
    const STUDENT = 'student';
    const PARENT = 'parent';
    const STAFF = 'staff';

    const DIGEST_NEVER = 0;
    const DIGEST_WEEKLY = 1;
    const DIGEST_DAILY = 2;

    /**
     * True if role is guest
     *
     * @return bool
     */
    public function isGuest()
    {
        return $this->type == self::GUEST;
    }

    /**
     * True if role is student
     *
     * @return bool
     */
    public function isStudent()
    {
        return $this->type == self::STUDENT;
    }

    /**
     * True if role is staff
     *
     * @return bool
     */
    public function isStaff()
    {
        return $this->type == self::STAFF;
    }

    /**
     *  True if role is staff
     *
     * @return bool
     */
    public function isParent()
    {
        return $this->type == self::PARENT;
    }

    /**
     * @param bool $canChangePassword
     * @return self
     */
    public function setCanChangePassword($canChangePassword)
    {
        $this->canChangePassword = $canChangePassword;
        return $this;
    }

    /**
     * @return bool
     */
    public function getCanChangePassword()
    {
        return $this->canChangePassword;
    }

    /**
     * Find a key/value representation of this model's data
     * and return it as a 2D array of value (an identifier)
     * and label (a human-readable description, usually)
     *
     * return [
     *     'value' => ...
     *     'label' => ...
     * ];
     *
     * @return array
     */
    public function findValueLabelPair()
    {
        return [
            'value' => $this->getId(),
            'label' => $this->getName(),
        ];
    }


    /* Getters/Setters
     */

    /**
     * @param int $id
     * @return self
     */
    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param string $name
     * @return self
     */
    public function setName($name)
    {
        $this->name = $name;
        return $this;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param string $type
     * @return self
     */
    public function setType($type)
    {
        $this->type = $type;
        return $this;
    }

    /**
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @param  string $digestSchedule
     * @return self
     */
    public function setDigestSchedule($digestSchedule)
    {
        $this->digestSchedule = $digestSchedule;
        return $this;
    }

    /**
     * @return string
     */
    public function getDigestSchedule()
    {
        return $this->digestSchedule;
    }

}
