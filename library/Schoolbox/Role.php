<?php

/**
 * Schoolbox user role model
 *
 * @author      James Leckie <james.leckie@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox;

use JsonSerializable;
use Schoolbox\Mapper\Traits\Model as ModelTrait;

class Role implements ValueLabelInterface, ISoftDeletable, JsonSerializable, Sequential
{
    use ModelTrait\CreatedAtDate;
    use ModelTrait\UpdatedAtDate;
    use ModelTrait\DeletedAtDate;
    use ModelTrait\UpdatedBy;
    use ModelTrait\CreateCollection;
    use ModelTrait\HasSequence;

    /** @var int */
    private $id;

    /** @var string */
    private $name;

    /**
     * @var string Type of user group, guest, student, parent, staff
     */
    private $type;

    /**
     * @var bool Users can change passwords
     */
    private $canChangePassword;

    /**
     * @var bool Users can view student profile (for staff)
     */
    private bool $viewStudentProfile;

    /**
     * @var bool Users can view student grade (for staff)
     */
    private bool $viewStudentGrades;

    /**
     * @var bool Users can view student calendar (for staff)
     */
    private bool $viewStudentCalendar;

    /**
     * @var bool Users can view parent profile (for staff)
     */
    private bool $viewParentProfile;

    /**
     * @var bool Users can access eportfolio (for staff)
     */
    private bool $accessEportfolio;

    /**
     * @var bool Users can access pastoral (for staff)
     */
    private bool $accessPastoral;

    /**
     * @var bool Users can create badges (for staff)
     */
    private bool $createBadges;

    /**
     * @var bool Users can create evidence (for staff)
     */
    private bool $accessEvidence;

    /**
     * @var bool Users can view parent directory (for staff)
     */
    private bool $viewParentDirectory;

    /**
     * @var bool Users can view staff directory (for staff)
     */
    private bool $viewStaffDirectory;

    /**
     * What's the digest schedule for this group?
     * self::DIGEST_NEVER or a cron schedule
     *
     * @var string|null
     */
    protected ?string $digestSchedule = self::DIGEST_NEVER;

    /**
     * Role Types
     * Every role has a type
     */
    public const GUEST   = 'guest';
    public const STUDENT = 'student';
    public const PARENT  = 'parent';
    public const STAFF   = 'staff';

    public const DIGEST_NEVER = '0';

    public const PERMISSION_VIEW_STUDENT_PROFILE  = 'viewStudentProfile';
    public const PERMISSION_VIEW_STUDENT_GRADES   = 'viewStudentGrades';
    public const PERMISSION_VIEW_STUDENT_CALENDAR = 'viewStudentCalendar';
    public const PERMISSION_VIEW_PARENT_PROFILE   = 'viewParentProfile';
    public const PERMISSION_ACCESS_EPORTFOLIO     = 'accessEportfolio';
    public const PERMISSION_ACCESS_PASTORAL       = 'accessPastoral';
    public const PERMISSION_CREATE_BADGES         = 'createBadges';
    public const PERMISSION_ACCESS_EVIDENCE       = 'accessEvidence';
    public const PERMISSION_VIEW_PARENT_DIRECTORY = 'viewParentDirectory';
    public const PERMISSION_VIEW_STAFF_DIRECTORY  = 'viewStaffDirectory';
    public const PERMISSION_CHANGE_PASSWORD       = 'canChangePassword';

    public const VALID_PERMISSIONS = [
        self::STAFF   => [
            self::PERMISSION_CHANGE_PASSWORD,
            self::PERMISSION_VIEW_STUDENT_PROFILE,
            self::PERMISSION_VIEW_STUDENT_GRADES,
            self::PERMISSION_VIEW_STUDENT_CALENDAR,
            self::PERMISSION_VIEW_PARENT_PROFILE,
            self::PERMISSION_ACCESS_EPORTFOLIO,
            self::PERMISSION_ACCESS_PASTORAL,
            self::PERMISSION_CREATE_BADGES,
            self::PERMISSION_ACCESS_EVIDENCE,
            self::PERMISSION_VIEW_PARENT_DIRECTORY,
            self::PERMISSION_VIEW_STAFF_DIRECTORY,
        ],
        self::STUDENT => [
            self::PERMISSION_CHANGE_PASSWORD,
        ],
        self::PARENT  => [
            self::PERMISSION_CHANGE_PASSWORD,
        ],
        self::GUEST   => [
        ],
    ];

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
     * @param bool|null $canChangePassword
     * @return self
     */
    public function setCanChangePassword(?bool $canChangePassword): Role
    {
        $this->canChangePassword = $canChangePassword;
        return $this;
    }

    /**
     * @return bool|null
     */
    public function getCanChangePassword(): ?bool
    {
        return $this->canChangePassword;
    }

    /**
     * @param bool $viewStudentProfile
     * @return static
     */
    public function setViewStudentProfile(bool $viewStudentProfile): self
    {
        $this->viewStudentProfile = $viewStudentProfile;
        return $this;
    }

    /**
     * @return bool
     */
    public function getViewStudentProfile(): bool
    {
        return $this->viewStudentProfile;
    }

    /**
     * @param bool $viewStudentGrades
     * @return static
     */
    public function setViewStudentGrades(bool $viewStudentGrades): self
    {
        $this->viewStudentGrades = $viewStudentGrades;
        return $this;
    }

    /**
     * @return bool
     */
    public function getViewStudentGrades(): bool
    {
        return $this->viewStudentGrades;
    }

    /**
     * @param bool $viewStudentCalendar
     * @return static
     */
    public function setViewStudentCalendar(bool $viewStudentCalendar): self
    {
        $this->viewStudentCalendar = $viewStudentCalendar;
        return $this;
    }

    /**
     * @return bool
     */
    public function getViewStudentCalendar(): bool
    {
        return $this->viewStudentCalendar;
    }

    /**
     * @param bool $viewParentProfile
     * @return static
     */
    public function setViewParentProfile(bool $viewParentProfile): self
    {
        $this->viewParentProfile = $viewParentProfile;
        return $this;
    }

    /**
     * @return bool
     */
    public function getViewParentProfile(): bool
    {
        return $this->viewParentProfile;
    }


    /**
     * @param bool $accessEportfolio
     * @return static
     */
    public function setAccessEportfolio(bool $accessEportfolio): self
    {
        $this->accessEportfolio = $accessEportfolio;
        return $this;
    }

    /**
     * @return bool
     */
    public function getAccessEportfolio(): bool
    {
        return $this->accessEportfolio;
    }

    /**
     * @param bool $accessPastoral
     * @return static
     */
    public function setAccessPastoral(bool $accessPastoral): self
    {
        $this->accessPastoral = $accessPastoral;
        return $this;
    }

    /**
     * @return bool
     */
    public function getAccessPastoral(): bool
    {
        return $this->accessPastoral;
    }


    /**
     * @param bool $createBadges
     * @return static
     */
    public function setCreateBadges(bool $createBadges): self
    {
        $this->createBadges = $createBadges;
        return $this;
    }

    /**
     * @return bool
     */
    public function getCreateBadges(): bool
    {
        return $this->createBadges;
    }

    /**
     * @param bool $accessEvidence
     * @return static
     */
    public function setAccessEvidence(bool $accessEvidence): self
    {
        $this->accessEvidence = $accessEvidence;
        return $this;
    }


    /**
     * @return bool
     */
    public function getAccessEvidence(): bool
    {
        return $this->accessEvidence;
    }


    /**
     * @param bool $viewParentDirectory
     * @return static
     */
    public function setViewParentDirectory(bool $viewParentDirectory): self
    {
        $this->viewParentDirectory = $viewParentDirectory;
        return $this;
    }

    /**
     * @return bool
     */
    public function getViewParentDirectory(): bool
    {
        return $this->viewParentDirectory;
    }

    /**
     * @param bool $viewStaffDirectory
     * @return static
     */
    public function setViewStaffDirectory(bool $viewStaffDirectory): self
    {
        $this->viewStaffDirectory = $viewStaffDirectory;
        return $this;
    }

    /**
     * @return bool
     */
    public function getViewStaffDirectory(): bool
    {
        return $this->viewStaffDirectory;
    }

    /**
     * @param int|null $id
     * @return self
     */
    public function setId(?int $id): Role
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @return int|null
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @param string|null $name
     * @return self
     */
    public function setName(?string $name): Role
    {
        $this->name = $name;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * @param string|null $type
     * @return self
     */
    public function setType(?string $type): Role
    {
        $this->type = $type;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getType(): ?string
    {
        return $this->type;
    }

    /**
     * @param string|null $digestSchedule
     * @return self
     */
    public function setDigestSchedule(?string $digestSchedule): Role
    {
        $this->digestSchedule = $digestSchedule;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getDigestSchedule(): ?string
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
