<?php
/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Pastoral;

use Alaress\Database\Predicate\Builder as PredicateBuilder;
use Alaress\Database\Quoter\IQuoter;
use Alaress_Date_Format;
use Alaress_Date_NullDate;
use PDO;
use PDOStatement;
use Schoolbox\Collection;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Mapper\DefinitionException;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Pastoral\Access\Mapper as PastoralAccessMapper;
use Schoolbox\Pastoral\Attachment\Mapper as PastoralAttachmentMapper;
use Schoolbox\Pastoral\Audit\Mapper as PastoralAuditMapper;
use Schoolbox\Pastoral\SubType\Mapper as PastoralSubTypeMapper;
use Schoolbox\Pastoral\Comment\Mapper as PastoralCommentMapper;
use Schoolbox\Pastoral\Tag\Mapper as PastoralTagMapper;
use Schoolbox\Pastoral\Tag\Model as PastoralTag;
use Schoolbox\Pastoral\Type\Mapper as PastoralTypeMapper;
use Schoolbox\Pastoral\Revision\Mapper as PastoralRevisionMapper;
use Schoolbox\Pastoral\Revision\Model as PastoralRevision;
use Schoolbox\Pastoral\Severity\Mapper as PastoralSeverityMapper;
use Schoolbox\Pastoral\Severity\Model as PastoralSeverity;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Search\Results;
use Schoolbox\User;
use Schoolbox\UserMapper;

/**
 * @method Model createModel($idValue = null)
 * @method Model hydrate(array $row, $alias = '', $callback = null)
 * @method Model findOneByColumn($columnName, $columnValue, $order = [], $options = [])
 */
class Mapper extends BaseMapper
{
    /** @var array $filters Used in report builder only */
    private $filters = [];

    /** @var array $bindings Used in report builder only */
    private $bindings = [];

    /** @var int $placeholderIndex This is a used to make sure parameters in report filters are given unique names */
    private static $placeholderIndex = 0;

    /**
     * @inheritdoc
     */
    protected function mapping()
    {
        $this->hasTable('pastoral');
        $this->hasModel(Model::class);
        $this->joins('parent_id', self::class, 'id', null, null, 'parent', 'parent');
        $this->joins('type_id', PastoralTypeMapper::class, 'id', null, null, 'type', 'type');
        $this->joins('subtype_id', PastoralSubTypeMapper::class, 'id', null, null, 'subtype', 'subtype');
        $this->joins('severity_id', PastoralSeverityMapper::class, 'id', null, null, 'severity', 'severity');
        $this->joins('owner_id', UserMapper::class, 'id', null, null, 'owner', 'owner');
        $this->joins('modified_by_user_id', UserMapper::class, 'id', null, null, 'modifiedBy', 'modifiedBy');
        $this->joins('student_id', UserMapper::class, 'id', null, null, 'student', 'student');
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('external_id', self::TYPE_INTEGER);
        $this->uses('body_private', self::TYPE_STRING);
        $this->uses('body_public', self::TYPE_STRING);
        $this->uses('heading', self::TYPE_STRING);
        $this->uses('points', self::TYPE_DECIMAL);
        $this->uses('private', self::TYPE_BOOLEAN);
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);

        $this->joinsReverseMulti('pastoral_id', PastoralAccessMapper::class, 'access', 'access');
        $this->joinsReverseMulti('pastoral_id', PastoralCommentMapper::class, 'comments', 'comments');
        $this->joinsReverseMulti('pastoral_id', PastoralAttachmentMapper::class, 'attachments', 'attachments');
        $this->joinsReverseMulti('pastoral_id', PastoralTagMapper::class, 'tags', 'tags');
        $this->joinsReverseMulti('root_id', PastoralRevisionMapper::class, 'revisions', 'revisions');

        $this->registerCallback(Mapper::CALLBACK_POSTSAVE, [$this, 'saveRevision']);
        $this->registerCallback(Mapper::CALLBACK_PRESOFTDELETE, [$this, 'deleteReferences']);
    }

    /**
     * Prior to deleting, make sure we delete anything that is linked to this record
     *
     * @param Model $model
     */
    public function deleteReferences(Model $model)
    {
        // Delete comments from record

        $this->getJoinMapper('comments')->deleteByColumn('pastoral_id', $model->getId());
        $this->getJoinMapper('attachments')->deleteByColumn('pastoral_id', $model->getId());
        $this->getJoinMapper('access')->deleteByColumn('pastoral_id', $model->getId());

        // Delete related tags

        $pastoralPastoralTagMapper = PastoralTagMapper::getInstance();
        $pastoralPastoralTagMapper->deleteForRecord($model);
        $pastoralPastoralTagMapper->purgeUnassociated();
    }

    /**
     * Prior to saving changes to a record, we cover out butts and keep a backup in the revision table for posterity
     *
     * @param Model $model
     */
    public function saveRevision(Model $model)
    {
        // Only save the revision if we are updating an existing record

        $pastoralRevisionMapper = PastoralRevisionMapper::getInstance();
        $pastoralRevision       = $pastoralRevisionMapper->createRevision($model);
        $pastoralRevisionMapper->save($pastoralRevision);
    }

    /**
     * @inheritdoc
     */
    protected function getSelectString()
    {
        /* * We do not need to join to deleted severity as the system does not permit soft-deletion
         *   of severities if they are used by a non-soft deleted pastoral.
         *
         * * We do not need to join to deleted types as the system does not permit soft-deletion
         *   of types if they are used by a non-soft deleted pastoral.
         *
         * * We do not need to join to deleted categories as the system does not permit soft-deletion
         *   of categories if they are used by a non-soft deleted pastoral.
         */

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

        return $this->hydrateSelectInternal($statement);
    }

    /**
     * Hydrate the record. This exists to prevent recursion when hydrating related records.
     *
     * @param PDOStatement $statement
     * @param bool         $hydrateRelatedRecords
     *
     * @return Collection|Model[]
     */
    protected function hydrateSelectInternal(PDOStatement $statement, $hydrateRelatedRecords = true)
    {
        // Return pastoral records fully hydrated by default

        $collection = $this->createCollection();

        foreach ($statement as $row) {
            $model = $this->hydrateRow($row, $hydrateRelatedRecords);

            if ($model instanceof Model) {
                $collection[$model->getId()] = $model;
            }
        }

        return $collection;
    }

    /**
     * @param $row
     * @param $hydrateRelatedRecords
     *
     * @return Model|bool
     * @throws DefinitionException If a specified mapper is not found in definition
     * @throws MapperException If unable to hydrate a row
     */
    protected function hydrateRow($row, $hydrateRelatedRecords)
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

        $model = $this->hydrate($row);

        $model->setOwner($ownerMapper->hydrate($row, 'o'));
        $model->setModifiedBy($modifiedByMapper->hydrate($row, 'mb'));
        $model->setStudent($studentMapper->hydrate($row, 's')->loadParents()->loadTeachers());
        $model->setAccess($this->getJoinMapper('access')->findByColumn('pastoral_id', $model->getId()));

        if ($this->getConfigLoader()->get('pastoral_severity')) {
            $model->setSeverity($severityMapper->hydrate($row, 'sv'));
        }

        $model->setType($typeMapper->hydrate($row, 't'));
        $model->setSubType($subtypeMapper->hydrate($row, 'c'));

        // If the public body looks empty, make it really empty

        if (empty(trim(strip_tags($model->getBodyPublic())))) {
            $model->setBodyPublic("");
        }

        $model->setTags(PastoralTagMapper::getInstance()->findForRecord($model));
        $model->setComments($this->getJoinMapper('comments')->findByColumn('pastoral_id', $model->getId()));

        $revisionMapper = PastoralRevisionMapper::getInstance();
        $revisions      = $revisionMapper->findByColumn('root_id', $model->getId());
        $model->setRevisions($revisions);

        // Only hydrate related records if we're specifically told to
        if ($hydrateRelatedRecords) {
            $model->setRelatedRecords($this->getRelatedRecords($model));
        }

        // Return hydrated model
        return $model;
    }

    /**
     * Filter records from the collection if the current user doesn't have access to them.
     * If the user is not in the audience and is not the owner, they can't see the record.
     *
     * @param Model $record A pastoral record
     * @param User  $user   The user who is trying to view them
     *
     * @return Model|null
     */
    public function filterRecordForUser(Model $record, User $user)
    {
        if (!$record instanceof Model) {
            return null;
        }

        if ($this->isUserInPastoralService($user) || $user->getId() == $record->getOwner()->getId()) {
            // Pastoral moderator and owner see all, shortcut out of here
            return $record;
        }

        // We need to iterate all access records to see if any of them grants private access

        $hasAccess = false;

        foreach ($record->getAccess() as $access) {
            if ($access->getAccessLevel($user) == PastoralAccessMapper::ACCESS_PUBLIC) {
                $hasAccess = true;
            } elseif ($access->getAccessLevel($user) == PastoralAccessMapper::ACCESS_PRIVATE) {
                // Found a record with private access, shortcut true return
                return $record;
            }
        }

        if ($hasAccess) {
            // Only has public access, strip the private body
            $record->setBodyPrivate(null);

            return $record;
        }

        return null;
    }

    /**
     * Filter records from the collection if the current user doesn't have access to them.
     * If the user is not in the audience and is not the owner, they can't see the record.
     *
     * @param Collection|Model[] $records A list of pastoral records
     * @param User               $user    The user who is trying to view them
     *
     * @return Collection|Model[]
     */
    public function filterRecordsForUser(Collection $records, User $user)
    {
        $collection = $this->createCollection();

        /** @var Model $record */
        foreach ($records as $record) {
            $record = $this->filterRecordForUser($record, $user);

            if ($record instanceof Model) {
                $collection->add($record);
            }
        }

        return $collection;
    }

    /**
     * Return any records immediately related to the supplied pastoral record
     *
     * @param Model $record
     *
     * @return Collection
     */
    public function getRelatedRecords(Model $record)
    {
        $query = $this->getSelectString();

        if ($record instanceof PastoralRevision) {
            $tableName = 'pastoral_revision';
        } else {
            $tableName = 'pastoral';
        }

        if ($record->getParent() instanceof Model) {
            // This is a child item, find its parent and its other siblings
            $query .= "                
                WHERE (
                    `{$tableName}`.`parent_id` = :parent_id_0 OR 
                    `{$tableName}`.`id` = :parent_id_1 OR 
                    `{$tableName}`.`parent_id` = :id_0
                ) AND
                   `{$tableName}`.`id` <> :id_1
                 AND `{$tableName}`.deleted_at IS NULL
            ";

            $bindings['id_0']        = $record->getId();
            $bindings['id_1']        = $record->getId();
            $bindings['parent_id_0'] = $record->getParent()->getId();
            $bindings['parent_id_1'] = $record->getParent()->getId();
        } else {
            // This is a parent item, just find its children

            $query .= "
                WHERE       `{$tableName}`.`parent_id` = :parent_id AND 
                            `{$tableName}`.`deleted_at` IS NULL
            ";

            $bindings['parent_id'] = $record->getId();
        }

        $statement = $this->database->prepare($query);
        $statement->execute($bindings);

        // Return hydrated objects- but don't hydrate their children or we'll end up in a recursive loop

        return $this->hydrateSelectInternal($statement, false);
    }

    /**
     * Returns a collection of students who have pastoral records linked to this student
     *
     * @param User $student
     *
     * @return array
     */
    public function getLinkedStudents($student)
    {
        $statement = $this->database->prepare("
            SELECT DISTINCT   `student_id`,
              COUNT(*) AS `count`,
              `user`.`lastname` AS `last_name`,
              `user`.`firstname` AS `first_name`
            FROM              `pastoral` p
              JOIN              `user` ON `user`.`id` = `student_id`
            WHERE             `p`.`deleted_at` IS NULL AND
                              `user`.`deleted_at` IS NULL AND
                              (
                                p.`parent_id` IN (
                                  SELECT `id`
                                  FROM pastoral
                                  WHERE
                                    `deleted_at` IS NULL AND (
                                      `student_id` = :student_id_0) OR `pastoral`.`id` IN (SELECT `parent_id`
                                                                                           FROM pastoral
                                                                                           WHERE `student_id` = :student_id_1 AND `deleted_at` IS NULL))
            
                                OR
                                  p.`id` IN (
                                    SELECT `id`
                                    FROM pastoral
                                    WHERE
                                      `deleted_at` IS NULL AND (
                                        `student_id` = :student_id_2) OR `pastoral`.`id` IN (SELECT `parent_id`
                                                                                             FROM pastoral
                                                                                             WHERE `student_id` = :student_id_3 AND `deleted_at` IS NULL)
                                  )
                              )
              AND student_id <> :student_id_4
            GROUP BY `student_id`
            ORDER BY `count` DESC, `user`.`lastname`, `user`.`firstname`            
        ");

        $statement->execute([
            ':student_id_0' => $student->getId(),
            ':student_id_1' => $student->getId(),
            ':student_id_2' => $student->getId(),
            ':student_id_3' => $student->getId(),
            ':student_id_4' => $student->getId(),
        ]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Display the most recent records that are tagged for display on dashboard based on their severity
     *
     * @param User $user
     * @param int  $count Number of recent records to display
     *
     * @return Collection|Model[]
     */
    public function findRecentDashboard(User $user, $count = 5)
    {
        return $this->filterRecordsForUser(
            $this->findByColumnList([
                'sv.dashboard' => 1,
            ], [
                'limit' => $count,
                ['order' => ['created_at' => 'DESC']],
            ]),
            $user
        );
    }

    /**
     * Display the most recent records that are tagged for display on dashboard based on their severity
     *
     * @param PastoralTag $tag
     *
     * @return Collection
     */
    public function findByTag($tag)
    {
        $sql = $this->getSelectString() . "
        
            JOIN `pastoral_tags` ON 
                `pastoral_tags`.`pastoral_id` = `pastoral`.`id` AND 
                `pastoral_tags`.`tag_id` = :tagId AND
                `pastoral_tags`.`deleted_at` IS NULL
        ";

        $statement = $this->database->prepare($sql);
        $statement->execute(['tagId' => $tag->getId()]);

        return $this->hydrateSelect($statement);
    }

    /**
     * Display the most recent records that are tagged for display on dashboard based on their severity
     *
     * @param User     $student
     * @param User     $linkedStudent
     * @param int|null $typeId
     *
     * @return Collection
     */
    public function findLinked($student, $linkedStudent, $typeId = null)
    {
        $sql = $this->getSelectString() . "
            WHERE 
            
            `pastoral`.`student_id` = :studentId1 AND `pastoral`.`id` IN (
                SELECT      `parent_id` 
                FROM        `pastoral` `pastoralLinked1` 
                WHERE       `pastoralLinked1`.`student_id` = :linkedStudentId1
            ) 
            
            OR

            `pastoral`.`student_id` = :linkedStudentId2 AND `pastoral`.`id` IN (
                SELECT      `parent_id` 
                FROM        `pastoral` `pastoralLinked2` 
                WHERE       `pastoralLinked2`.`student_id` = :studentId2
            )
        ";

        if (!empty($typeId)) {
            $sql .= " AND type_id = :typeId";
        }

        $sql .= "
            ORDER BY `pastoral`.`created_at` DESC
        ";

        $statement = $this->database->prepare($sql);

        $params = [
            'studentId1' => $student->getId(),
            'linkedStudentId1' => $linkedStudent->getId(),
            'studentId2' => $student->getId(),
            'linkedStudentId2' => $linkedStudent->getId(),
        ];

        if (!empty($typeId)) {
            $params['typeId'] = $typeId;
        }

        $statement->execute($params);
        $results = $this->hydrateSelect($statement);

        return $results;
    }

    /**
     * Perform a search of pastoral records (currently searches the heading, body and comments)
     *
     * @param string $search
     * @param User   $user The user who is searching
     *
     * @return Collection|Model[]
     */
    public function getSearchResults($search, User $user)
    {
        $search = trim($search);

        // Keep an audit trail of searches
        $pastoralAuditMapper = PastoralAuditMapper::getInstance();
        $auditRecord         = $pastoralAuditMapper->createModel();
        $auditRecord->setUser($user);
        $auditRecord->setSearch($search);

        $words = [];

        /** @var IQuoter $database */
        $database = $this->getDatabase();

        foreach (explode(' ', $search) as $word) {
            if (strlen($word) >= Results::MIN_SEARCH_LENGTH) {
                $words[] = "%{$database->escapeForLike($word)}%";
            }
        }

        if (count($words)) {
            // Search public heading & body of pastoral record always...

            $pbRecordSearch = $this->getPredicateBuilder();
            $pbRecordSearch->like('pastoral.body_public', $words);
            $pbRecordSearch->like('pastoral.heading', $words);
            $recordBodyAndHeadingClause = implode(' OR ', $pbRecordSearch->getWhere());

            // Search private body...

            $pbPrivateBodySearch = $this->getPredicateBuilder();
            $pbPrivateBodySearch->like('pastoral.body_private', $words);
            $recordPrivateBodyClause = implode(' OR ', $pbPrivateBodySearch->getWhere());

            // ...Only with these additional caveats to ensure user has access

            // ...Is owner

            $pbOwnerSearch = $this->getPredicateBuilder();
            $pbOwnerSearch->equals('pastoral.owner_id', $user->getId());
            $recordOwnerClause = implode(' OR ', $pbOwnerSearch->getWhere());

            // ...Is in group with private access

            $pbGroupSearch = $this->getPredicateBuilder();
            $pbGroupSearch->equals('pastoral_access.private', 1);
            $pbGroupSearch->isNull('pastoral_access.deleted_at');
            $recordPrivateGroupClause = implode(' OR ', $pbGroupSearch->getWhere());

            // ...Where the user is a member of the group

            $pbGroupMemberSearch = $this->getPredicateBuilder();
            $pbGroupMemberSearch->equals('pastoral_group.user_id', $user->getId());
            $pbGroupMemberSearch->isNull('pastoral_group.deleted_at');
            $recordPrivateGroupMemberClause = implode(' OR ', $pbGroupMemberSearch->getWhere());

            // ...Is in role with private access

            $pbRoleSearch = $this->getPredicateBuilder();
            $pbRoleSearch->equals('pastoral_access.role_id', $user->getRole()->getId());
            $pbRoleSearch->equals('pastoral_access.private', 1);
            $pbRoleSearch->isNull('pastoral_access.deleted_at');
            $recordPrivateRoleClause = implode(' OR ', $pbRoleSearch->getWhere());

            // ...Is an individual user with private access

            $pbUserSearch = $this->getPredicateBuilder();
            $pbUserSearch->equals('pastoral_access.user_id', $user->getId());
            $pbUserSearch->equals('pastoral_access.private', 1);
            $pbUserSearch->isNull('pastoral_access.deleted_at');
            $recordPrivateUserClause = implode(' OR ', $pbUserSearch->getWhere());

            // Ensure record is not deleted

            $recordDeletionClause = $this->getPredicateBuilder()
                                        ->isNull('pastoral.deleted_at')
                                        ->getWhere()[0];

            // Search comments

            $pbCommentSearch = $this->getPredicateBuilder();
            $pbCommentSearch->like('pastoral_comment.body', $words);
            $commentBodyClause = implode(' OR ', $pbCommentSearch->getWhere());

            // Ensure comment is not deleted

            $commentDeleteClause = $this->getPredicateBuilder()
                                       ->isNull('pastoral_comment.deleted_at')
                                       ->getWhere()[0];

            $sql = $this->getSelectString() . "
                WHERE 
                (
                    (
                        ($recordBodyAndHeadingClause) OR (
                            ($recordPrivateBodyClause) AND (
                                pastoral.id IN (
                                    SELECT  pastoral_id 
                                    FROM    pastoral_access 
                                    WHERE   ($recordOwnerClause)
                                )
                            )
                        )
                    ) 
                    OR
                    (
                        ($recordPrivateBodyClause) AND (
                            pastoral.id IN (
                                SELECT  pastoral_id 
                                FROM    pastoral_access 
                                WHERE   ($recordPrivateGroupClause) AND 
                                        group_id IN (
                                            SELECT group_id 
                                            FROM pastoral_group 
                                            WHERE ($recordPrivateGroupMemberClause)
                                        )
                            )
                        )
                    ) 
                    OR
                    (
                        $recordPrivateBodyClause AND (
                            pastoral.id IN (
                                SELECT  pastoral_id 
                                FROM    pastoral_access 
                                WHERE   ($recordPrivateRoleClause)
                            )
                        )
                    ) 
                    OR
                    (
                        $recordPrivateBodyClause AND (
                            pastoral.id IN (
                                SELECT  pastoral_id 
                                FROM    pastoral_access 
                                WHERE   ($recordPrivateUserClause)
                            )
                        )
                    ) 
                    OR
                    (
                        pastoral.id IN (
                            SELECT  pastoral_id 
                            FROM    pastoral_comment 
                            WHERE   ($commentBodyClause) AND 
                                    ($commentDeleteClause)
                        )
                    )
                )
                AND ($recordDeletionClause)                
            ";

            // Bind the parameters
            $statement = $this->database->prepare($sql);

            // Retrieve the hydrated records
            $statement->execute(array_merge(
                $pbRecordSearch->getParams(),
                $pbCommentSearch->getParams(),
                $pbPrivateBodySearch->getParams(),
                $pbOwnerSearch->getParams(),
                $pbGroupSearch->getParams(),
                $pbGroupMemberSearch->getParams(),
                $pbRoleSearch->getParams(),
                $pbUserSearch->getParams()
            ));

            $results = $this->filterRecordsForUser($this->hydrateSelect($statement), $user);

            // Keep a record of all searches performed by users, and what results were displayed
            $searchResults = [];

            /** @var Model $record */
            foreach ($results as $result) {
                $searchResults[] = $result->getId();
            }

            // Record the search results that were returned by the search by imploding their IDs down into CSV
            if (count($searchResults) > 0) {
                $auditRecord->setSearchResult(implode(',', $searchResults));
            }
        } else {
            // All search words were too short
            $results = $this->createCollection();
        }

        // Save the audit record of the search
        $pastoralAuditMapper->save($auditRecord);

        return $results;
    }

    /**
     * Ugly helper function to create report SQL condition
     *
     * @param Collection $collection
     * @param string     $field
     */
    private function addFilter(Collection $collection, $field)
    {
        $ids = [];

        foreach ($collection as $current) {
            if (is_object($current) && method_exists($current, 'getId')) {
                $ids[] = $current->getId();
            } else {
                $ids[] = $current;
            }
        }

        if (count($ids) > 0) {
            $pbArray = PredicateBuilder::getArrayBindings(
                $ids,
                str_replace(".", "", str_replace("`", "", str_replace("_", "", $field))) . self::$placeholderIndex
            );

            $this->bindings  = array_merge($this->bindings, $pbArray['bindings']);
            $this->filters[] = "{$field} IN ({$pbArray['placeholders']})";
        }

        self::$placeholderIndex++;
    }

    /**
     * This is the function that gets the report results out of the database
     *
     * @param User                  $user
     * @param Alaress_Date_Format   $before_date
     * @param Alaress_Date_Format   $after_date
     * @param null|PastoralSeverity $severity
     * @param Collection            $types
     * @param Collection            $authors
     * @param Collection            $students
     * @param Collection            $tags
     * @param Collection            $order
     * @param bool                  $involved
     * @param Collection            $years
     *
     * @return Collection|Model[]
     */
    public function getReportData(
        User $user,
        Alaress_Date_Format $before_date,
        Alaress_Date_Format $after_date,
        PastoralSeverity $severity = null,
        Collection $types,
        Collection $authors,
        Collection $students,
        Collection $tags,
        Collection $order,
        $involved,
        Collection $years
    ) {
        $this->bindings = [];
        $this->filters  = [];

        $orderSql = '';

        if ($before_date instanceof Alaress_Date_Format && !$before_date instanceof Alaress_Date_NullDate) {
            $this->bindings[':beforeDate'] = $before_date->endOfDay()->toIso8601String();
            $this->filters[]               = '`created_at` <= :beforeDate';
        }

        if ($after_date instanceof Alaress_Date_Format && !$after_date instanceof Alaress_Date_NullDate) {
            $this->bindings[':afterDate'] = $after_date->startOfDay()->toIso8601String();
            $this->filters[]              = '`created_at` >= :afterDate';
        }

        if (!empty($severity) && $this->getConfigLoader()->get('pastoral_severity')) {
            $this->bindings[':severity'] = $severity->getId();
            $this->filters[]             = '`sv`.`order` >= :severity';
        }

        $this->addFilter($students, '`student_id`');
        $this->addFilter($years, '`s`.`year`');
        $this->addFilter($types, '`type_id`');
        $this->addFilter($authors, '`owner_id`');

        $orderParts = [];
        foreach ($order as $orderCurrent) {
            switch ($orderCurrent) {
                case 'student':
                    $orderParts[] = "`s`.lastname, `s`.firstname";
                    break;
                case 'severity':
                    $orderParts[] = "`sv`.order";
                    break;
                case 'date':
                    $orderParts[] = "`pastoral`.created_at desc";
                    break;
                case 'subtype':
                    $orderParts[] = "`c`.name";
                    break;
                case 'points':
                    $orderParts[] = "`pastoral`.points";
                    break;
                case 'year':
                    $orderParts[] = "`s`.year";
                    break;
                case 'author':
                    $orderParts[] = "`o`.lastname, `o`.firstname";
                    break;
            }
        }

        if (!empty($orderParts)) {
            $orderSql = ' ORDER BY ' . implode(',', $orderParts);
        }

        // If filtering on involvement, only show records the logged in user is involved with via audience/role/ownership

        if ($involved || !$this->isUserInPastoralService($user)) {
            $this->filters[] = "
                (
                    (SELECT COUNT(*) FROM `pastoral_access` WHERE `role_id` = {$user->getRole()->getId()} AND `pastoral_id` = `pastoral`.`id`) > 0 OR
                    (SELECT COUNT(*) FROM `pastoral_access` WHERE `user_id` = {$user->getId()} AND `pastoral_id` = `pastoral`.`id`) > 0 OR
                    (
                        SELECT COUNT(*) FROM `pastoral_access` WHERE `group_id` IN (
                            SELECT `group_id` 
                            FROM `pastoral_group_member` 
                            WHERE `user_id` = {$user->getId()}
                        ) AND `pastoral_id` = `pastoral`.`id`
                    ) > 0 OR
                    `pastoral`.`owner_id` = {$user->getId()}
                )
            ";
        }

        $criteriaParts = [];
        foreach ($this->filters as $filter) {
            $criteriaParts[] = $filter;
        }

        $criteriaSql = ' WHERE pastoral.deleted_at IS NULL ';

        if (!empty($criteriaParts)) {
            $criteriaSql .= ' AND' . implode(' AND ', $criteriaParts);
        }

        // Execute SQL

        $statement = $this->getDatabase()->prepare($this->getSelectString() . "{$criteriaSql} {$orderSql}");
        $statement->execute($this->bindings);

        // Hydrate records for return

        $results = $this->hydrateSelect($statement);

        // Filter out any I can't view
        $results = $this->filterRecordsForUser($results, $user);

        // If there are no tags to filter, just return the results now

        if ($tags->count() == 0) {
            return $results;
        }

        // Otherwise, check each record has at least one of the tags we are searching for

        $pastoralPastoralTagMapper = PastoralTagMapper::getInstance();
        $resultsFiltered           = $this->createCollection();

        /** @var Model $result */
        foreach ($results as $result) {
            $found      = false;
            $tagsRecord = $pastoralPastoralTagMapper->findForRecord($result);

            /** @var PastoralTag $tagFilter */
            foreach ($tags as $tagFilter) {
                /** @var PastoralTag $tagRecord */
                foreach ($tagsRecord as $tagRecord) {
                    if ($tagRecord->getId() == $tagFilter->getId()) {
                        $found = true;
                        break;
                    }
                }

                if ($found) {
                    $resultsFiltered->add($result);
                    break;
                }
            }
        }

        return $resultsFiltered;
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
}
