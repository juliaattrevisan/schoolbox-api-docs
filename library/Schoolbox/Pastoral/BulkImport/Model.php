<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Pastoral\BulkImport;

use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Mapper\Traits\Model\Id;
use Schoolbox\Mapper\Traits\Model\UpdatedAtDate;
use Schoolbox\Pastoral\Severity\Model as PastoralSeverity;
use Schoolbox\Pastoral\SubType\Model as PastoralSubType;
use Schoolbox\Pastoral\Type\Model as PastoralType;
use Schoolbox\Resource\Storage\Model as StorageModel;
use Schoolbox\User;

/**
 * Model for pastoral bulk import records.
 *
 * This defines a template from which records will be created.
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
     * The points assigned to this record, if any.
     */
    protected ?float $points = null;
}
