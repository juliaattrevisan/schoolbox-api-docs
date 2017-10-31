<?php
/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Pastoral\Revision;

use PDOStatement;
use Schoolbox\Collection;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Mapper\DefinitionException;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Mapper\Exception\Deleted;
use Schoolbox\Pastoral\Access\Mapper as PastoralAccessMapper;
use Schoolbox\Pastoral\Attachment\Mapper as PastoralAttachmentMapper;
use Schoolbox\Pastoral\Comment\Mapper as PastoralCommentMapper;
use Schoolbox\Pastoral\Mapper as PastoralMapper;
use Schoolbox\Pastoral\Model as Pastoral;
use Schoolbox\Pastoral\Revision\Mapper as PastoralRevisionMapper;
use Schoolbox\Pastoral\Revision\Model as PastoralRevision;
use Schoolbox\Pastoral\Severity\Mapper as PastoralSeverityMapper;
use Schoolbox\Pastoral\SubType\Mapper as PastoralSubTypeMapper;
use Schoolbox\Pastoral\Tag\Mapper as PastoralTagMapper;
use Schoolbox\Pastoral\Type\Mapper as PastoralTypeMapper;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\User;
use Schoolbox\UserMapper;

class Mapper extends BaseMapper
{
    /**
     * @inheritdoc
     */
    protected function mapping()
    {
        $this->hasTable('pastoral_revision');
        $this->hasModel(Model::class);
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
        $this->uses('private', self::TYPE_BOOLEAN);
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
     * @inheritdoc
     */
    protected function getSelectString($revisions = false)
    {
        return "
            SELECT      {$this->getColumnString()},
                        {$this->getJoinColumnString('student', 's')},
                        {$this->getJoinColumnString('severity', 'sv')},
                        {$this->getJoinColumnString('owner', 'o')},
                        {$this->getJoinColumnString('type', 't')},
                        {$this->getJoinColumnString('modifiedBy', 'mb')},
                        {$this->getJoinColumnString('subtype', 'c')}
            FROM        {$this->getTable()}
            INNER JOIN   {$this->getJoinSql('student', '', 's', true)}
            LEFT  JOIN   {$this->getJoinSql('severity', '', 'sv')}
            INNER JOIN   {$this->getJoinSql('owner', '', 'o', true)}
            LEFT JOIN    {$this->getJoinSql('modifiedBy', '', 'mb', true)}
            INNER JOIN   {$this->getJoinSql('type', '', 't', true)}
            LEFT  JOIN   {$this->getJoinSql('subtype', '', 'c')}
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
            $collection->add($revision);
        }

        return $collection;
    }

    /**
     * This is also used by the PastoralRevision class to hydrate a record
     *
     * @param $row
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

        $model->setOwner($ownerMapper->hydrate($row, 'o'));
        $model->setModifiedBy($modifiedByMapper->hydrate($row, 'mb'));
        $model->setStudent($studentMapper->hydrate($row, 's')->loadParents()->loadTeachers());
        $model->setAccess($this->getJoinMapper('access')->findByColumn('pastoral_id', $model->getRootId()));

        // If there is no user in the session this call is coming from the notifier

        if (isset($this->sessionData['user'])) {
            $user = $this->getSessionUser();
        } else {
            // The notifier does not need access to anything other than the student ID and the record ID, so for
            // safety reasons the remainder of the details on the record will be stripped.

            $model->setBodyPublic(null);
            $model->setBodyPrivate(null);
            $model->setAttachments(new Collection());
            $model->setRevisions(new Collection());
            $model->setTags(new Collection());
            $model->setComments(new Collection());
            $model->setRelatedRecords(new Collection());

            return $model;
        }

        if ($this->getConfigLoader()->get('pastoral_severity')) {
            $model->setSeverity($severityMapper->hydrate($row, 'sv'));
        }

        $model->setType($typeMapper->hydrate($row, 't'));
        $model->setSubType($subtypeMapper->hydrate($row, 'c'));

        // If the user is not in the audience- and is not the owner, they can't see the record

        if ($this->isUserInPastoralService($user) ||                  // Pastoral moderators
            $model->getOwner()->getId() == $user->getId()) {          // The records owner
            $accessLevel = PastoralAccessMapper::ACCESS_PRIVATE;      // Get private access
        } else {
            $accessLevel = PastoralAccessMapper::ACCESS_NONE;

            foreach ($model->getAccess() as $access) {
                $currentAccessLevel = $access->getAccessLevel($user);

                if ($currentAccessLevel > $accessLevel) {
                    $accessLevel = $currentAccessLevel;
                }

                // We are as high as we are going to get, stop looking

                if ($accessLevel == PastoralAccessMapper::ACCESS_PRIVATE) {
                    break;
                }
            }
        }

        if ($accessLevel == PastoralAccessMapper::ACCESS_NONE) {
            // No access to the record

            return false;
        } elseif ($accessLevel == PastoralAccessMapper::ACCESS_PUBLIC) {
            // Access to the public record details only, blank out the private body and attachments

            $model->setBodyPrivate(null);
            $model->setAttachments($this->getJoinMapper('attachments')->findByColumnList([
                'pastoral_id' => $model->getId(),
                'private'     => 0,
            ]));
        } elseif ($accessLevel == PastoralAccessMapper::ACCESS_PRIVATE) {
            // Access to the whole record

            $model->setAttachments(
                $this->getJoinMapper('attachments')
                    ->findByColumn('pastoral_id', $model->getId())
            );

            // If the private body looks empty, make it really empty

            if (empty(trim(strip_tags($model->getBodyPrivate())))) {
                $model->setBodyPrivate("");
            }
        }

        // If the public body looks empty, make it really empty

        if (empty(trim(strip_tags($model->getBodyPublic())))) {
            $model->setBodyPublic("");
        }

        $model->setTags(PastoralTagMapper::getInstance()->findForRecord($model));
        $model->setComments($this->getJoinMapper('comments')->findByColumn('pastoral_id', $model->getId()));

        // Return hydrated model

        return $model;
    }

    /**
     * Does the given user have access to the pastoral service?
     *
     * @param User $user
     *
     * @return bool
     */
    private function isUserInPastoralService(User $user)
    {
        return $user->getAccessList()->filterBy('service', UserAccess::SERVICE_PASTORAL)->count() > 0;
    }

    /**
     * Create a revision record from a pastoral record
     *
     * @param Pastoral $pastoral
     *
     * @return PastoralRevision
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
                [],
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
}
