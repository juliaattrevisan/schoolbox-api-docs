<?php
/**
 * Please don't use this mapper directly in web requests!
 *
 * Use `\Schoolbox\Pastoral\Revision\DataSource` instead; it wraps this mapper with
 * calls that automatically filter and censor records and fields that a user
 * does not have permission to view.
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Pastoral\Revision;

use Alaress_Date_Format;
use PDOStatement;
use Schoolbox\Collection;
use Schoolbox\Mapper\DefinitionException;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Mapper\Exception\Deleted;
use Schoolbox\Pastoral\Access\Mapper as PastoralAccessMapper;
use Schoolbox\Pastoral\Attachment\Mapper as PastoralAttachmentMapper;
use Schoolbox\Pastoral\BaseMapper as BaseMapper;
use Schoolbox\Pastoral\Comment\Mapper as PastoralCommentMapper;
use Schoolbox\Pastoral\Mapper as PastoralMapper;
use Schoolbox\Pastoral\Model as Pastoral;
use Schoolbox\Pastoral\Revision\Mapper as PastoralRevisionMapper;
use Schoolbox\Pastoral\Revision\Model as PastoralRevision;
use Schoolbox\Pastoral\Severity\Mapper as PastoralSeverityMapper;
use Schoolbox\Pastoral\SubType\Mapper as PastoralSubTypeMapper;
use Schoolbox\Pastoral\Tag\Mapper as PastoralTagMapper;
use Schoolbox\Pastoral\Type\Mapper as PastoralTypeMapper;
use Schoolbox\UserMapper;

class Mapper extends BaseMapper
{
    /**
     * @inheritdoc
     */
    protected function mapping()
    {
        $this->hasTable('pastoral_revision');
        $this->hasModel(PastoralRevision::class);
        $this->joins('parent_id', self::class, 'id', null, null, 'parent', 'parent');
        $this->joins('type_id', PastoralTypeMapper::class, 'id', null, null, 'type', 'type');
        $this->joins('subtype_id', PastoralSubTypeMapper::class, 'id', null, null, 'subtype', 'subtype');
        $this->joins('severity_id', PastoralSeverityMapper::class, 'id', null, null, 'severity', 'severity');
        $this->joins('owner_id', UserMapper::class, 'id', null, null, 'owner', 'owner');
        $this->joins('modified_by_user_id', UserMapper::class, 'id', null, null, 'modifiedBy', 'modifiedBy');
        $this->joins('student_id', UserMapper::class, 'id', null, null, 'student', 'student');
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('root_id', self::TYPE_INTEGER);
        $this->uses('external_id', self::TYPE_INTEGER);
        $this->uses('body_private', self::TYPE_STRING);
        $this->uses('body_public', self::TYPE_STRING);
        $this->uses('heading', self::TYPE_STRING);
        $this->uses('points', self::TYPE_DECIMAL);
        $this->uses('created_at', self::TYPE_DATETIME);
        $this->uses('updated_at', self::TYPE_DATETIME);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);

        $this->joinsReverseMulti('pastoral_id', PastoralAccessMapper::class, 'access', 'access');
        $this->joinsReverseMulti('pastoral_id', PastoralCommentMapper::class, 'comments', 'comments');
        $this->joinsReverseMulti('pastoral_id', PastoralAttachmentMapper::class, 'attachments', 'attachments');
        $this->joinsReverseMulti('pastoral_id', PastoralTagMapper::class, 'tags', 'tags');
        $this->joinsReverseMulti('root_id', PastoralRevisionMapper::class, 'revisions', 'revisions');
    }

    /**
     * Finds all revisions for a pastoral care record.
     *
     * @param Pastoral $record
     * @return Collection|Model[]
     */
    public function findForRecord(Pastoral $record): Collection
    {
        return $this->findByColumn('root_id', $record->getId(), ['order' => ['updated_at' => 'desc']]);
    }

    /**
     * @inheritdoc
     */
    protected function getSelectString(): string
    {
        return "
            SELECT      {$this->getColumnString()},
                        {$this->getJoinColumnString('student', 's')},
                        {$this->getJoinColumnString('severity', 'sv')},
                        {$this->getJoinColumnString('owner', 'o')},
                        {$this->getJoinColumnString('type', 't')},
                        {$this->getJoinColumnString('modifiedBy', 'mb')},
                        {$this->getJoinColumnString('subtype', 'c')}
        ";
    }

    /**
     * @inheritdoc
     */
    protected function getFromString(): string
    {
        return "
            FROM        {$this->getTable()}
            INNER JOIN   {$this->getJoinSql('student', '', 's', true)}
            LEFT  JOIN   {$this->getJoinSql('severity', '', 'sv', true)}
            INNER JOIN   {$this->getJoinSql('owner', '', 'o', true)}
            LEFT JOIN    {$this->getJoinSql('modifiedBy', '', 'mb', true)}
            INNER JOIN   {$this->getJoinSql('type', '', 't', true)}
            LEFT  JOIN   {$this->getJoinSql('subtype', '', 'c', true)}
        ";
    }

    /**
     * @inheritdoc
     */
    protected function hydrateSelect(PDOStatement $statement)
    {
        // This internal function exists to stop recursion when hydrating related records

        // Return pastoral records fully hydrated by default

        $collection = $this->createCollection();

        foreach ($statement as $row) {
            $revision = $this->hydrateRow($row);
            if ($revision instanceof Model) {
                $collection->add($revision);
            }
        }

        return $collection;
    }

    /**
     * This is also used by the PastoralRevision class to hydrate a record
     *
     * @param array $row
     *
     * @return Model|bool
     * @throws DefinitionException If a specified mapper is not found in definition
     * @throws MapperException If unable to hydrate a row
     */
    protected function hydrateRow($row)
    {
        /** @var UserMapper $ownerMapper */
        $ownerMapper = $this->getJoinMapper('owner');

        /** @var UserMapper $modifiedByMapper */
        $modifiedByMapper = $this->getJoinMapper('modifiedBy');

        /** @var UserMapper $studentMapper */
        $studentMapper = $this->getJoinMapper('student');

        /** @var PastoralSeverityMapper $severityMapper */
        $severityMapper = $this->getJoinMapper('severity');

        /** @var PastoralTypeMapper $typeMapper */
        $typeMapper = $this->getJoinMapper('type');

        /** @var PastoralSubTypeMapper $subtypeMapper */
        $subtypeMapper = $this->getJoinMapper('subtype');

        /** @var Model $model */
        $model = $this->hydrate($row);

        if ($this->getConfigLoader()->get('pastoral_severity')) {
            $model->setSeverity($severityMapper->hydrate($row, 'sv'));
        }

        $model->setOwner($ownerMapper->hydrate($row, 'o'));
        $model->setModifiedBy($modifiedByMapper->hydrate($row, 'mb'));
        $model->setStudent($studentMapper->hydrate($row, 's')->loadParents()->loadTeachers());
        $model->setAccess($this->getJoinMapper('access')->findByColumn('pastoral_id', $model->getRootId()));
        $model->setType($typeMapper->hydrate($row, 't'));
        $model->setSubType($subtypeMapper->hydrate($row, 'c'));
        $model->setTags(PastoralTagMapper::getInstance()->findForRecord($model));
        $model->setComments($this->getJoinMapper('comments')->findByColumn('pastoral_id', $model->getId()));

        // If the private body looks empty, make it really empty

        if (empty(trim(strip_tags($model->getBodyPrivate())))) {
            $model->setBodyPrivate("");
        }

        // If the public body looks empty, make it really empty

        if (empty(trim(strip_tags($model->getBodyPublic())))) {
            $model->setBodyPublic("");
        }

        // Return hydrated model

        return $model;
    }

    /**
     * @param Pastoral $pastoral
     *
     * @return Model
     * @throws MapperException
     */
    public function createRevision(Pastoral $pastoral)
    {
        // Super-laziness follows

        // We need to re-fetch the pastoral record from the database to get the correct timestamps
        $pastoralMapper = PastoralMapper::getInstance();

        try {
            $pastoral = $pastoralMapper->findOneByColumn(
                $pastoralMapper->getIdentifierColumn(),
                $pastoral->getId(),
                ['cache' => false]
            );
        } catch (Deleted $e) {
            $pastoral = $e->getDeletedObject();
        } catch (MapperException $e) {
            // NOOP- this is an exception case for pastoral importing
        }

        /** @var PastoralRevision $revision */
        $revision = $this->createModel();

        foreach (get_class_methods(Pastoral::class) as $method) {
            if (0 === strpos($method, 'set')) {
                // We need to put the pastoral `id` into the revisions `root_id` field, everything else just get copied as-is

                if ($method !== 'setId') {
                    $getter    = $method;
                    $getter[0] = 'g';
                    if (is_callable([$pastoral, $getter]) && is_callable([$revision, $method])) {
                        $revision->$method($pastoral->$getter());
                    }
                } else {
                    $revision->setRootId($pastoral->getId());
                }
            }
        }

        return $revision;
    }


    /**
     * Overridden because this mapper doesn't actually have an updated
     * timestamp field (presumably so the timestamp can be set manually?).
     *
     * @param Model                    $object
     * @param Alaress_Date_Format|null $updatedAt
     */
    public function resetUpdatedTimestamp($object, ?Alaress_Date_Format $updatedAt): void
    {
        $this->database->query(
            "
                UPDATE {$this->table}
                SET    updated_at = :updatedAt
                WHERE  root_id    = :id
                ORDER BY id DESC
                LIMIT 1
            ",
            [
                'id'        => $object->getId(),
                'updatedAt' => ($updatedAt && !$updatedAt->isNull()) ? $updatedAt->getDatabase() : null,
            ]
        );
    }

    /**
     * @inheritDoc
     */
    public function raiseNotification($object, $verb, $previous = null)
    {
        return true;
    }
}
