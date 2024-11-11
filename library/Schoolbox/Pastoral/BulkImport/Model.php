<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Pastoral\BulkImport;

use Alaress_Date_Format;
use Schoolbox\GenericCollection;
use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Mapper\Traits\Model\Id;
use Schoolbox\Mapper\Traits\Model\UpdatedAtDate;
use Schoolbox\Pastoral\Severity\Model as PastoralSeverity;
use Schoolbox\Pastoral\SubType\Model as PastoralSubType;
use Schoolbox\Pastoral\Tag\Model as TagModel;
use Schoolbox\Pastoral\Type\Model as PastoralType;
use Schoolbox\Resource\Storage\Model as StorageModel;
use Schoolbox\User;

/**
 * Model for pastoral bulk import records.
 *
 * This defines a template from which records will be created.
 *
 * @phpstan-type ImportLog array{
 *     known: array<
 *         array{
 *             files: array<string>,
 *             user: array{id: int, externalId: string, name: string},
 *             record: ?array{id: int}
 *         }
 *     >,
 *     unknown: array<
 *         array{
 *             file: string,
 *             reason: string,
 *             user: array{externalId: ?string},
 *         },
 *     >,
 * }
 */
class Model implements ISoftDeletable
{
    use Id;
    use CreatedAtDate;
    use UpdatedAtDate;
    use DeletedAtDate;

    /**
     * The user who created the bulk import.
     */
    private User $owner;

    /**
     * The ZIP file from which to import files to attach to each pastoral
     * record created by this import.
     *
     * This file may be temporary, as it's only useful as an import.
     */
    private StorageModel $zip;

    /**
     * The template which is used to extract student external IDs from the files
     * within `$this->zip`.
     */
    private string $filePathTemplate;

    /**
     * The severity of records created for the import.
     *
     * Must be provided if severity is enabled.
     */
    private ?PastoralSeverity $severity = null;

    /**
     * The type of records created for this bulk import.
     */
    protected PastoralType $type;

    /**
     * The subtype (if any) of records created for this bulk import.
     *
     * Must be a valid subtype of the selected type, or null if the type has no
     * subtypes.
     */
    private ?PastoralSubType $subtype = null;

    /**
     * The heading of records created for this bulk import.
     */
    private string $heading;

    /**
     * The public body of records created for this bulk import.
     *
     * Placeholder values are supported but not translated: they will be
     * translated in the actual imported records.
     */
    private string $bodyPublic;

    /**
     * The points to be assigned records created for this import, if any.
     */
    private ?float $points = null;

    /**
     * The date at which this bulk import was actually imported, and pastoral
     * records were actually created.
     *
     * Null if this import has not been run yet.
     */
    private ?Alaress_Date_Format $importedAt = null;

    /**
     * The user who initiated the import, if anyone.
     */
    private ?User $importedBy = null;

    /**
     * The import log for this bulk import, in JSON format.
     *
     * @var ImportLog|null
     */
    private ?array $importLog = null;

    /**
     * The date at which this bulk import was undone, or null if this import has
     * not been undone.
     */
    private ?Alaress_Date_Format $undoneAt = null;

    /**
     * The user who undid the import, if anyone.
     */
    private ?User $undoneBy = null;

    /**
     * Access records for this bulk import.
     *
     * @var GenericCollection<Access\Model>
     */
    private GenericCollection $access;

    /**
     * Tags for this bulk import.
     *
     * @var GenericCollection<TagModel>
     */
    private GenericCollection $tags;

    /**
     * Constructor: creates empty access and tag collections.
     */
    public function __construct()
    {
        $this->access = new GenericCollection(Access\Model::class);
        $this->tags   = new GenericCollection(TagModel::class);
    }

    /**
     * @return User
     */
    public function getOwner(): User
    {
        return $this->owner;
    }

    /**
     * @param User $owner
     * @return $this
     */
    public function setOwner(User $owner): self
    {
        $this->owner = $owner;
        return $this;
    }

    /**
     * @return StorageModel
     */
    public function getZip(): StorageModel
    {
        return $this->zip;
    }

    /**
     * @param StorageModel $zip
     * @return $this
     */
    public function setZip(StorageModel $zip): self
    {
        $this->zip = $zip;
        return $this;
    }

    /**
     * @return string
     */
    public function getFilePathTemplate(): string
    {
        return $this->filePathTemplate;
    }

    /**
     * @param string $filePathTemplate
     * @return $this
     */
    public function setFilePathTemplate(string $filePathTemplate): self
    {
        $this->filePathTemplate = $filePathTemplate;
        return $this;
    }

    /**
     * @return PastoralSeverity|null
     */
    public function getSeverity(): ?PastoralSeverity
    {
        return $this->severity;
    }

    /**
     * @param PastoralSeverity|null $severity
     * @return $this
     */
    public function setSeverity(?PastoralSeverity $severity): self
    {
        $this->severity = $severity;
        return $this;
    }

    /**
     * @return PastoralType
     */
    public function getType(): PastoralType
    {
        return $this->type;
    }

    /**
     * @param PastoralType $type
     * @return $this
     */
    public function setType(PastoralType $type): self
    {
        $this->type = $type;
        return $this;
    }

    /**
     * @return PastoralSubType|null
     */
    public function getSubtype(): ?PastoralSubType
    {
        return $this->subtype;
    }

    /**
     * @param PastoralSubType|null $subtype
     * @return $this
     */
    public function setSubtype(?PastoralSubType $subtype): self
    {
        $this->subtype = $subtype;
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
     * @param string $heading
     * @return $this
     */
    public function setHeading(string $heading): self
    {
        $this->heading = $heading;
        return $this;
    }

    /**
     * @return string
     */
    public function getBodyPublic(): string
    {
        return $this->bodyPublic;
    }

    /**
     * @param string $bodyPublic
     * @return $this
     */
    public function setBodyPublic(string $bodyPublic): self
    {
        $this->bodyPublic = $bodyPublic;
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
     * @param float|null $points
     * @return $this
     */
    public function setPoints(?float $points): self
    {
        $this->points = $points;
        return $this;
    }

    /**
     * @return Alaress_Date_Format|null
     */
    public function getImportedAt(): ?Alaress_Date_Format
    {
        return $this->importedAt;
    }

    /**
     * @param Alaress_Date_Format|null $importedAt
     * @return $this
     */
    public function setImportedAt(?Alaress_Date_Format $importedAt): self
    {
        $this->importedAt = $importedAt;
        return $this;
    }

    /**
     * Has this already been imported?
     *
     * @return bool
     */
    public function isImported(): bool
    {
        return $this->importedAt !== null;
    }

    /**
     * @return User|null
     */
    public function getImportedBy(): ?User
    {
        return $this->importedBy;
    }

    /**
     * @param User|null $importedBy
     * @return $this
     */
    public function setImportedBy(?User $importedBy): self
    {
        $this->importedBy = $importedBy;
        return $this;
    }

    /**
     * @return ImportLog|null
     */
    public function getImportLog(): ?array
    {
        return $this->importLog;
    }

    /**
     * @param ImportLog|null $importLog
     * @return $this
     */
    public function setImportLog(?array $importLog): self
    {
        $this->importLog = $importLog;
        return $this;
    }

    /**
     * @return Alaress_Date_Format|null
     */
    public function getUndoneAt(): ?Alaress_Date_Format
    {
        return $this->undoneAt;
    }

    /**
     * @param Alaress_Date_Format|null $undoneAt
     * @return $this
     */
    public function setUndoneAt(?Alaress_Date_Format $undoneAt): self
    {
        $this->undoneAt = $undoneAt;
        return $this;
    }

    /**
     * Has this already been imported?
     *
     * @return bool
     */
    public function isUndone(): bool
    {
        return $this->undoneAt !== null;
    }

    /**
     * @return User|null
     */
    public function getUndoneBy(): ?User
    {
        return $this->undoneBy;
    }

    /**
     * @param User|null $undoneBy
     * @return $this
     */
    public function setUndoneBy(?User $undoneBy): self
    {
        $this->undoneBy = $undoneBy;
        return $this;
    }

    /**
     * @return GenericCollection<Access\Model>
     */
    public function getAccess(): GenericCollection
    {
        return $this->access;
    }

    /**
     * @param GenericCollection<Access\Model> $access
     * @return $this
     */
    public function setAccess(GenericCollection $access): self
    {
        $this->access = $access;
        return $this;
    }

    /**
     * @return GenericCollection<TagModel>
     */
    public function getTags(): GenericCollection
    {
        return $this->tags;
    }

    /**
     * @param GenericCollection<TagModel> $tags
     * @return $this
     */
    public function setTags(GenericCollection $tags): self
    {
        $this->tags = $tags;
        return $this;
    }
}
