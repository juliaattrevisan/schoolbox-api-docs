<?php

/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Pastoral;

use Alaress_Date_Format;
use Schoolbox\GenericCollection;
use Schoolbox\IModel;
use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model\CreateCollection;
use Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Mapper\Traits\Model\Id;
use Schoolbox\Mapper\Traits\Model\UpdatedAtDate;
use Schoolbox\Pastoral\SubType\Model as PastoralSubType;
use Schoolbox\Pastoral\Revision\Model as PastoralRevision;
use Schoolbox\Pastoral\Severity\Model as PastoralSeverity;
use Schoolbox\Pastoral\Type\Model as PastoralType;
use Schoolbox\Role;
use Schoolbox\User;

class Model implements ISoftDeletable, IModel
{
    use Id;
    use CreatedAtDate;
    use UpdatedAtDate;
    use DeletedAtDate;
    use CreateCollection;

    /** @var Alaress_Date_Format|null $updatedAtDisplay The date this record, or any of its accessible comments were last updated */
    private ?Alaress_Date_Format $updatedAtDisplay = null;

    /** @var null|int Identifier of corresponding SIS record. NULL if none. */
    private ?int $externalId = null;

    /** @var null|Model Null if does not have a parent. */
    private ?Model $parent = null;

    /**@var PastoralType */
    protected PastoralType $type;

    /** @var null|PastoralSubType Optional */
    private ?PastoralSubType $subtype = null;

    /** @var null|PastoralSeverity Optional. Will be NULL if admin has configured 'pastoral_severity' to off. */
    private ?PastoralSeverity $severity = null;

    /** @var User|null - Null if record is created from class list */
    protected ?User $student;

    /** @var string */
    protected string $heading;

    /** @var bool */
    protected bool $private;

    /** @var string|null */
    protected ?string $bodyPrivate = null;

    /** @var string|null */
    protected ?string $bodyPublic = null;

    /** @var null|float */
    protected ?float $points = null;

    /**
     * The user who created the record.
     */
    private User $owner;

    private User $modifiedBy;

    /** @var GenericCollection<Access\Model> */
    private GenericCollection $access;

    /** @var GenericCollection<Comment\Model> */
    private GenericCollection $comments;

    /** @var GenericCollection<Attachment\Model> */
    private GenericCollection $attachments;

    /** @var GenericCollection<Tag\Model> */
    private GenericCollection $tags;

    /** @var GenericCollection<RecordAction\Model> */
    private GenericCollection $recordActions;

    /** @var GenericCollection<Model> */
    private GenericCollection $relatedRecords;

    /** @var GenericCollection<PastoralRevision> */
    private GenericCollection $revisions;

    private ?Alaress_Date_Format $occurredOn = null;
    private ?Alaress_Date_Format $expiresAt = null;

    /**
     * Model constructor
     */
    public function __construct()
    {
        $this->access         = new GenericCollection(Access\Model::class);
        $this->comments       = new GenericCollection(Comment\Model::class);
        $this->attachments    = new GenericCollection(Attachment\Model::class);
        $this->tags           = new GenericCollection(Tag\Model::class);
        $this->recordActions  = new GenericCollection(RecordAction\Model::class);
        $this->relatedRecords = new GenericCollection(Model::class);
        $this->revisions      = new GenericCollection(PastoralRevision::class);
    }

    /**
     * @param GenericCollection<Access\Model> $access
     *
     * @return self
     */
    public function setAccess(GenericCollection $access): self
    {
        $this->access = $access;

        return $this;
    }

    /**
     * @return GenericCollection<Access\Model>|null
     */
    public function getAccess(): ?GenericCollection
    {
        return $this->access;
    }

    /**
     * Returns access with role based permission
     * @return GenericCollection<Access\Model>
     */
    public function getAccessWithPermission(): GenericCollection
    {
        $accessWithPermission = clone $this->access;
        foreach ($accessWithPermission as $key => $access) {
            if ($access->getUser() && $access->getUser()->isStaffWithPastoralAccess()) {
                continue;
            }
            $role = $access->getRole() ?: ($access->getUser() ? $access->getUser()->getRole() : null);
            if ($role && in_array(Role::PERMISSION_ACCESS_PASTORAL, Role::VALID_PERMISSIONS[$role->getType()]) && !$role->getAccessPastoral()) {
                $accessWithPermission->offsetUnset($key);
            }
        }
        return $accessWithPermission;
    }

    /**
     * @param GenericCollection<Tag\Model> $tags
     *
     * @return self
     */
    public function setTags(GenericCollection $tags): self
    {
        $this->tags = $tags;

        return $this;
    }

    /**
     * @return GenericCollection<Tag\Model>
     */
    public function getTags(): GenericCollection
    {
        return $this->tags;
    }

    /**
     * @param GenericCollection<RecordAction\Model> $recordActions
     *
     * @return self
     */
    public function setRecordActions(GenericCollection $recordActions): self
    {
        $this->recordActions = $recordActions;

        return $this;
    }

    /**
     * @return GenericCollection<RecordAction\Model>
     */
    public function getRecordActions(): GenericCollection
    {
        return $this->recordActions;
    }

    /**
     * Returns uncompleted action count
     * @return int
     */
    public function getUncompletedActionCount(): int
    {
        return $this->getRecordActions()->filterBy('completed', 0)->count();
    }

    /**
     * @param string|null $bodyPrivate
     *
     * @return self
     */
    public function setBodyPrivate(?string $bodyPrivate): self
    {
        $this->bodyPrivate = $bodyPrivate;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getBodyPrivate(): ?string
    {
        return $this->bodyPrivate;
    }

    /**
     * @param string|null $bodyPublic
     *
     * @return self
     */
    public function setBodyPublic(?string $bodyPublic): self
    {
        $this->bodyPublic = $bodyPublic;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getBodyPublic(): ?string
    {
        return $this->bodyPublic;
    }

    /**
     * @param string $heading
     *
     * @return self
     */
    public function setHeading(string $heading): self
    {
        $this->heading = $heading;

        return $this;
    }

    /**
     * @return string
     */
    public function getHeading(): string
    {
        return $this->heading;
    }

    /**
     * @param float|null $points
     *
     * @return self
     */
    public function setPoints(?float $points): self
    {
        $this->points = $points;

        return $this;
    }

    /**
     * @return float|null
     */
    public function getPoints(): ?float
    {
        return $this->points;
    }

    /**
     * @param GenericCollection<Comment\Model> $comments
     *
     * @return self
     */
    public function setComments(GenericCollection $comments): self
    {
        $this->comments = $comments;

        return $this;
    }

    /**
     * @return GenericCollection<Comment\Model>
     */
    public function getComments(): GenericCollection
    {
        return $this->comments;
    }

    /**
     * @return GenericCollection<Attachment\Model>
     */
    public function getAttachments(): GenericCollection
    {
        return $this->attachments;
    }

    /**
     * @param GenericCollection<Attachment\Model> $attachments
     *
     * @return self
     */
    public function setAttachments(GenericCollection $attachments): self
    {
        $this->attachments = $attachments;

        return $this;
    }

    /**
     * @param int|null $externalId
     *
     * @return self
     */
    public function setExternalId(?int $externalId): self
    {
        $this->externalId = $externalId;

        return $this;
    }

    /**
     * @return null|int
     */
    public function getExternalId(): ?int
    {
        return $this->externalId;
    }

    /**
     * @param PastoralType $type
     *
     * @return self
     */
    public function setType(PastoralType $type): self
    {
        $this->type = $type;

        return $this;
    }

    /**
     * @return null|PastoralType
     */
    public function getType(): ?PastoralType
    {
        return $this->type;
    }

    /**
     * @param PastoralSubType|null $subtype
     *
     * @return self
     */
    public function setSubType(?PastoralSubType $subtype = null): self
    {
        $this->subtype = $subtype;

        return $this;
    }

    /**
     * @return null|PastoralSubType
     */
    public function getSubType(): ?PastoralSubType
    {
        return $this->subtype;
    }

    /**
     * @param Model|null $parent
     *
     * @return self
     */
    public function setParent(?Model $parent = null): self
    {
        $this->parent = $parent;

        return $this;
    }

    /**
     * @return null|Model
     */
    public function getParent(): ?Model
    {
        return $this->parent;
    }

    /**
     * @param PastoralSeverity|null $severity
     *
     * @return self
     */
    public function setSeverity(?PastoralSeverity $severity = null): self
    {
        $this->severity = $severity;

        return $this;
    }

    /**
     * @return null|PastoralSeverity
     */
    public function getSeverity(): ?PastoralSeverity
    {
        return $this->severity;
    }

    /**
     * @param User|null $student
     *
     * @return self
     */
    public function setStudent(?User $student): self
    {
        $this->student = $student;

        return $this;
    }

    /**
     * @return User|null
     */
    public function getStudent(): ?User
    {
        return $this->student;
    }

    /**
     * @param User $owner
     *
     * @return self
     */
    public function setOwner(User $owner): self
    {
        $this->owner = $owner;

        return $this;
    }

    /**
     * @return User
     */
    public function getOwner(): User
    {
        return $this->owner;
    }

    /**
     * @param Alaress_Date_Format $updatedAtDisplay
     *
     * @return self
     */
    public function setUpdatedAtDisplay(Alaress_Date_Format $updatedAtDisplay): self
    {
        $this->updatedAtDisplay = $updatedAtDisplay;

        return $this;
    }

    /**
     * @return Alaress_Date_Format
     */
    public function getUpdatedAtDisplay(): Alaress_Date_Format
    {
        // If this has not been calculated fall back to the records updated_at date

        if ($this->updatedAtDisplay === null) {
            return $this->updatedAt;
        }

        return $this->updatedAtDisplay;
    }

    /**
     * @param User $modifiedBy
     *
     * @return static
     */
    public function setModifiedBy(User $modifiedBy): self
    {
        $this->modifiedBy = $modifiedBy;

        return $this;
    }

    /**
     * @return User
     */
    public function getModifiedBy(): User
    {
        return $this->modifiedBy;
    }

    /**
     * @param GenericCollection<Model> $relatedRecords
     *
     * @return self
     */
    public function setRelatedRecords(GenericCollection $relatedRecords): self
    {
        $this->relatedRecords = $relatedRecords;

        return $this;
    }

    /**
     * @return GenericCollection<Model>
     */
    public function getRelatedRecords(): GenericCollection
    {
        return $this->relatedRecords;
    }

    /**
     * @return GenericCollection<PastoralRevision>
     */
    public function getRevisions(): GenericCollection
    {
        return $this->revisions;
    }

    /**
     * @param GenericCollection<PastoralRevision> $revisions
     *
     * @return self
     */
    public function setRevisions(GenericCollection $revisions): self
    {
        $this->revisions = $revisions;

        return $this;
    }

    /**
     * @return Alaress_Date_Format|null
     */
    public function getOccurredOn(): ?Alaress_Date_Format
    {
        return $this->occurredOn;
    }

    /**
     * @param Alaress_Date_Format|null $occurredOn
     * @return static
     */
    public function setOccurredOn(?Alaress_Date_Format $occurredOn): self
    {
        $this->occurredOn = $occurredOn;
        return $this;
    }

    /**
     * @return Alaress_Date_Format|null
     */
    public function getExpiresAt(): ?Alaress_Date_Format
    {
        return $this->expiresAt;
    }

    /**
     * @param Alaress_Date_Format|null $expiresAt
     * @return static
     */
    public function setExpiresAt(?Alaress_Date_Format $expiresAt): self
    {
        $this->expiresAt = $expiresAt;
        return $this;
    }

    /**
     * Return if the record is archived
     *
     * @return bool
     */
    public function isArchived(): bool
    {
        return $this->getExpiresAt() !== null && $this->getExpiresAt()->endOfDay()->isPast();
    }

    /**
     * Returns occurred on if set, created at otherwise
     * @return Alaress_Date_Format|null
     */
    public function getRecordDate(): ?Alaress_Date_Format
    {
        return $this->occurredOn ?? $this->createdAt;
    }
}
