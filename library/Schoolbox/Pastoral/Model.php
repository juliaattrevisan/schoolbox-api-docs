<?php
/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Pastoral;

use Alaress_Date_Format;
use Schoolbox\BaseModel;
use Schoolbox\Collection;
use Schoolbox\GenericCollection;
use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Mapper\Traits\Model\Id;
use Schoolbox\Mapper\Traits\Model\UpdatedAtDate;
use Schoolbox\Pastoral\SubType\Model as PastoralSubType;
use Schoolbox\Pastoral\Revision\Model as PastoralRevision;
use Schoolbox\Pastoral\Severity\Model as PastoralSeverity;
use Schoolbox\Pastoral\Type\Model as PastoralType;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\User;
use Schoolbox\Pastoral\Access\Mapper as PastoralAccessMapper;

/**
 * @method static GenericCollection|Model[] createCollection()
 */
class Model extends BaseModel implements ISoftDeletable
{
    use Id;
    use CreatedAtDate;
    use UpdatedAtDate;
    use DeletedAtDate;

    /** @var Alaress_Date_Format $updatedAtDisplay The date this record, or any of its accessible comments were last updated */
    private $updatedAtDisplay;

    /** @var User $modifiedByDisplay The person that last modified the record, or any of its accessible comments */
    private $modifiedByDisplay;

    /** @var null|int Identifier of corresponding SIS record. NULL if none. */
    private $externalId;

    /** @var null|Model Null if does not have a parent. */
    private $parent;

    /**@var PastoralType */
    protected $type;

    /** @var null|PastoralSubType Optional */
    private $subtype;

    /** @var null|PastoralSeverity Optional. Will be NULL if admin has configured 'pastoral_severity' to off. */
    private $severity;

    /** @var User */
    protected $student;

    /** @var string */
    protected $heading;

    /** @var bool */
    protected $private;

    /** @var string */
    protected $bodyPrivate;

    /** @var string */
    protected $bodyPublic;

    /** @var null|float */
    protected $points;

    /** @var User */
    protected $owner;

    /** @var null|User Null if imported from SIS */
    private $modifiedBy;

    /** @var Collection|Access\Model[] */
    private $access;

    /** @var Collection|Comment\Model[] */
    private $comments;

    /** @var Collection|Attachment\Model[] */
    private $attachments;

    /** @var Collection|Tag\Model[] */
    private $tags;

    /** @var Collection|Model[] */
    private $relatedRecords;

    /** @var Collection|PastoralRevision[] */
    private $revisions;


    /**
     * Model constructor
     */
    public function __construct()
    {
        // TODO tighten collection types
        $this->access = new Collection();
        $this->comments = new Collection();
        $this->attachments = new Collection();
        $this->tags = new Collection();
        $this->relatedRecords = new Collection();
        $this->revisions = new Collection();
    }

    /**
     * @param Collection|Access\Model[] $access
     *
     * @return self
     */
    public function setAccess(Collection $access)
    {
        $this->access = $access;

        return $this;
    }

    /**
     * @return null|GenericCollection|Access\Model[]
     */
    public function getAccess()
    {
        return $this->access;
    }

    /**
     * @param Collection|Tag\Model[] $tags
     *
     * @return self
     */
    public function setTags(Collection $tags)
    {
        $this->tags = $tags;

        return $this;
    }

    /**
     * @return Collection|Tag\Model[]
     */
    public function getTags()
    {
        return $this->tags;
    }

    /**
     * @param string|null $bodyPrivate
     *
     * @return self
     */
    public function setBodyPrivate($bodyPrivate)
    {
        $this->bodyPrivate = $bodyPrivate;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getBodyPrivate()
    {
        return $this->bodyPrivate;
    }

    /**
     * @param string|null $bodyPublic
     *
     * @return self
     */
    public function setBodyPublic($bodyPublic)
    {
        $this->bodyPublic = $bodyPublic;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getBodyPublic()
    {
        return $this->bodyPublic;
    }

    /**
     * @param string $heading
     *
     * @return self
     */
    public function setHeading($heading)
    {
        $this->heading = $heading;

        return $this;
    }

    /**
     * @return string
     */
    public function getHeading()
    {
        return $this->heading;
    }

    /**
     * @param float $points
     *
     * @return self
     */
    public function setPoints($points)
    {
        $this->points = $points;

        return $this;
    }

    /**
     * @return float
     */
    public function getPoints()
    {
        return $this->points;
    }

    /**
     * @param Collection|Comment\Model[] $comments
     *
     * @return self
     */
    public function setComments(Collection $comments)
    {
        $this->comments = $comments;

        return $this;
    }

    /**
     * @return Collection|Comment\Model[]
     */
    public function getComments()
    {
        return $this->comments;
    }

    /**
     * @return Collection|Attachment\Model[]
     */
    public function getAttachments()
    {
        return $this->attachments;
    }

    /**
     * @param Collection|Attachment\Model[] $attachments
     *
     * @return self
     */
    public function setAttachments(Collection $attachments)
    {
        $this->attachments = $attachments;

        return $this;
    }

    /**
     * @param null|int $externalId
     *
     * @return self
     */
    public function setExternalId($externalId)
    {
        $this->externalId = $externalId;

        return $this;
    }

    /**
     * @return null|int
     */
    public function getExternalId()
    {
        return $this->externalId;
    }

    /**
     * @param PastoralType $type
     *
     * @return self
     */
    public function setType(PastoralType $type)
    {
        $this->type = $type;

        return $this;
    }

    /**
     * @return PastoralType
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @param null|PastoralSubType $subtype
     *
     * @return self
     */
    public function setSubType(PastoralSubType $subtype = null)
    {
        $this->subtype = $subtype;

        return $this;
    }

    /**
     * @return null|PastoralSubType
     */
    public function getSubType()
    {
        return $this->subtype;
    }

    /**
     * @param null|Model $parent
     *
     * @return self
     */
    public function setParent(Model $parent = null)
    {
        $this->parent = $parent;

        return $this;
    }

    /**
     * @return null|Model
     */
    public function getParent()
    {
        return $this->parent;
    }

    /**
     * @param null|PastoralSeverity $severity
     *
     * @return self
     */
    public function setSeverity(PastoralSeverity $severity = null)
    {
        $this->severity = $severity;

        return $this;
    }

    /**
     * @return null|PastoralSeverity
     */
    public function getSeverity()
    {
        return $this->severity;
    }

    /**
     * @param User $student
     *
     * @return self
     */
    public function setStudent(User $student)
    {
        $this->student = $student;

        return $this;
    }

    /**
     * @return User
     */
    public function getStudent()
    {
        return $this->student;
    }

    /**
     * @param User $owner
     *
     * @return self
     */
    public function setOwner(User $owner)
    {
        $this->owner = $owner;

        return $this;
    }

    /**
     * @return User
     */
    public function getOwner()
    {
        return $this->owner;
    }

    /**
     * @param Alaress_Date_Format $updatedAtDisplay
     *
     * @return self
     */
    public function setUpdatedAtDisplay(Alaress_Date_Format $updatedAtDisplay)
    {
        $this->updatedAtDisplay = $updatedAtDisplay;

        return $this;
    }

    /**
     * @return Alaress_Date_Format
     */
    public function getUpdatedAtDisplay()
    {
        // If this has not been calculated fall back to the records updated_at date

        if ($this->updatedAtDisplay === null) {
            return $this->updatedAt;
        }

        return $this->updatedAtDisplay;
    }

    /**
     * @param User|null $modifiedByDisplay
     *
     * @return self
     */
    public function setModifiedByDisplay(?User $modifiedByDisplay = null)
    {
        $this->modifiedByDisplay = $modifiedByDisplay;

        return $this;
    }

    /**
     * @return User
     */
    public function getModifiedByDisplay()
    {
        // If this has not been set fall back to the last record modifier

        if ($this->modifiedByDisplay === null) {
            return $this->modifiedBy;
        }

        return $this->modifiedByDisplay;
    }

    /**
     * @param null|User $modifiedBy
     *
     * @return self
     */
    public function setModifiedBy(?User $modifiedBy = null)
    {
        $this->modifiedBy = $modifiedBy;

        return $this;
    }

    /**
     * @return null|User
     */
    public function getModifiedBy()
    {
        return $this->modifiedBy;
    }

    /**
     * @param Collection|Model[] $relatedRecords
     *
     * @return self
     */
    public function setRelatedRecords(Collection $relatedRecords)
    {
        $this->relatedRecords = $relatedRecords;

        return $this;
    }

    /**
     * @return Collection|Model[]
     */
    public function getRelatedRecords()
    {
        return $this->relatedRecords;
    }

    /**
     * @return Collection
     */
    public function getRevisions()
    {
        return $this->revisions;
    }

    /**
     * @param Collection|PastoralRevision[] $revisions
     *
     * @return self
     */
    public function setRevisions(Collection $revisions)
    {
        $this->revisions = $revisions;

        return $this;
    }

    /**
     * Return the specified user's access level to this record
     *
     * Order of preference:
     * 1. Pastoral Moderators and the record owner always have PRIVATE access
     * 2. If any access rule applies DENY access, then access level is DENY
     * 3. If no rules apply, then access level is DENY
     * 4. Rules apply, and none apply DENY, return  max access level
     *
     * @param User $user Must have services, role, and pastoral group memberships hydrated
     * @return int
     */
    public function getAccessLevel(User $user): int
    {
        // Owners and moderators get private access regardless of any other permission set
        if ($this->isUserInPastoralService($user) || (int)$user->getId() === (int)$this->getOwner()->getId()) {
            return PastoralAccessMapper::ACCESS_PRIVATE;
        }

        // Otherwise start with deny access, and work our way up the permission levels
        $accessLevel = PastoralAccessMapper::ACCESS_DENY;

        foreach ($this->getAccess() as $access) {
            $access->setPastoral($this);
            $accessLevelCurrent = $access->getAccessLevel($user);

            if ($accessLevelCurrent === null) {
                // The access rule didn't apply to this user, continue to the next one
                continue;
            }

            if ($accessLevelCurrent === PastoralAccessMapper::ACCESS_DENY) {
                return PastoralAccessMapper::ACCESS_DENY;
            }

            // We want the highest access level
            $accessLevel = max($accessLevel, $accessLevelCurrent);
        }

        return $accessLevel;
    }


    /**
     * Does the given user have access to the pastoral service?
     *
     * @param User $user
     *
     * @return bool
     */
    protected function isUserInPastoralService(User $user)
    {
        return $user->getAccessList()->filterBy('service', UserAccess::SERVICE_PASTORAL)->count() > 0;
    }
}
