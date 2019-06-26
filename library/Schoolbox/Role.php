<?php
/**
 * Schoolbox user role model
 *
 * @author      James Leckie <james.leckie@alaress.com.au>
 * @copyright   2013 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 */
namespace Schoolbox;

use JsonSerializable;
use Schoolbox\Mapper\Traits\Model as ModelTrait;

class Role implements ValueLabelInterface, ISoftDeletable, JsonSerializable
{
    use ModelTrait\DeletedAtDate;

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
     * self::DIGEST_NEVER or a cron schedule
     *
     * @var string
     */
    protected $digestSchedule;

    /**
     * Role Types
     * Every role has a type
     */
    public const GUEST = 'guest';
    public const STUDENT = 'student';
    public const PARENT = 'parent';
    public const STAFF = 'staff';

    public const DIGEST_NEVER = '0';


    /**
     * Return the set of role types
     *
     * @return string[]
     */
    public static function getRoleTypes(): array
    {
        return [
            self::STAFF,
            self::PARENT,
            self::STUDENT,
            self::GUEST,
        ];
    }

    /**
     * True if role is guest
     *
     * @return bool
     */
    public function isGuest(): bool
    {
        return $this->type === self::GUEST;
    }

    /**
     * True if role is student
     *
     * @return bool
     */
    public function isStudent(): bool
    {
        return $this->type === self::STUDENT;
    }

    /**
     * True if role is staff
     *
     * @return bool
     */
    public function isStaff(): bool
    {
        return $this->type === self::STAFF;
    }

    /**
     *  True if role is staff
     *
     * @return bool
     */
    public function isParent(): bool
    {
        return $this->type === self::PARENT;
    }

    /**
     * @param bool $canChangePassword
     * @return self
     */
    public function setCanChangePassword(bool $canChangePassword): Role
    {
        $this->canChangePassword = $canChangePassword;
        return $this;
    }

    /**
     * @return bool
     */
    public function getCanChangePassword(): bool
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
    public function findValueLabelPair(): array
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
    public function setId(int $id): Role
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @param string $name
     * @return self
     */
    public function setName(string $name): Role
    {
        $this->name = $name;
        return $this;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param string $type
     * @return self
     */
    public function setType(string $type): Role
    {
        $this->type = $type;
        return $this;
    }

    /**
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @param  string $digestSchedule
     * @return self
     */
    public function setDigestSchedule(string $digestSchedule): Role
    {
        $this->digestSchedule = $digestSchedule;
        return $this;
    }

    /**
     * @return string
     */
    public function getDigestSchedule(): string
    {
        return $this->digestSchedule;
    }


    /**
     * @return array
     */
    public function jsonSerialize(): array
    {
        return [
            'id'      => $this->id,
            'type'    => $this->type,
            'name'    => $this->name,
            'student' => $this->isStudent(),
            'staff'   => $this->isStaff(),
            'parent'  => $this->isParent(),
            'guest'   => $this->isGuest(),
        ];
    }
}
