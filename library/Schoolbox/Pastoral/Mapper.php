<?php
/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Pastoral;

use Alaress\Database\Predicate\Builder as PredicateBuilder;
use Alaress\Database\Quoter\IQuoter;
use Alaress_Date_Format;
use Alaress_Date_NullDate;
use PDOStatement;
use Schoolbox\Collection;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper\DefinitionException;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Pastoral\Access\Mapper as PastoralAccessMapper;
use Schoolbox\Pastoral\Attachment\Mapper as PastoralAttachmentMapper;
use Schoolbox\Pastoral\Comment\Mapper as PastoralCommentMapper;
use Schoolbox\Pastoral\Revision\Mapper as PastoralRevisionMapper;
use Schoolbox\Pastoral\Severity\Mapper as PastoralSeverityMapper;
use Schoolbox\Pastoral\Severity\Model as PastoralSeverity;
use Schoolbox\Pastoral\SubType\Mapper as PastoralSubTypeMapper;
use Schoolbox\Pastoral\Tag\Mapper as PastoralTagMapper;
use Schoolbox\Pastoral\Tag\Model as PastoralTag;
use Schoolbox\Pastoral\Type\Mapper as PastoralTypeMapper;
use Schoolbox\Resource\Search\Results;
use Schoolbox\User;
use Schoolbox\UserMapper;

/**
 * Please don't use this mapper directly in web requests!
 *
 * Use `\Schoolbox\Pastoral\DataSource` instead; it wraps this mapper with
 * calls that automatically filter and censor records and fields that a user
 * does not have permission to view.
 *
 * @method Model createModel($idValue = null)
 * @method Model hydrate(array $row, $alias = '', $callback = null)
 * @method Model findById($identifierValue)
 * @method Model findOneByColumn($columnName, $columnValue, array $options = [])
 * @method GenericCollection|Model[] findAll($options = [])
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
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);

        $this->joinsReverseMulti('pastoral_id', PastoralAccessMapper::class, 'access', 'access');
        $this->joinsReverseMulti('pastoral_id', PastoralCommentMapper::class, 'comments', 'comments');
        $this->joinsReverseMulti('pastoral_id', PastoralAttachmentMapper::class, 'attachments', 'attachments');
        $this->joinsReverseMulti('pastoral_id', PastoralTagMapper::class, 'tags', 'tags');
        $this->joinsReverseMulti('root_id', PastoralRevisionMapper::class, 'revisions', 'revisions');

        $this->registerCallback(self::CALLBACK_POSTSAVE, [$this, 'saveRevision']);
        $this->registerCallback(self::CALLBACK_PRESOFTDELETE, [$this, 'deleteReferences']);
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
     * @throws MapperException
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
         * * We do not need to join to deleted subtypes as the system does not permit soft-deletion
         *   of subtypes if they are used by a non-soft deleted pastoral.
         *
         * * We do not need to join to deleted students as pastoral records for a deleted user should not exist.
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
            INNER JOIN   {$this->getJoinSql('student', '', 's')}
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
     * @return GenericCollection
     * @throws MapperException
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
        $model->setComments($this->getJoinMapper('comments')->findByColumn('pastoral_id', $model->getId(), ['sort' => ['created_at', 'desc']]));

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
     * Returns a collection containing all records related to the supplied pastoral record (including itself)
     *
     * @param Model $record
     *
     * @return GenericCollection
     * @throws MapperException
     */
    public function getRelatedRecords(Model $record)
    {
        $query = $this->getSelectString();

        if ($record->getParent() instanceof Model) {
            // This is a child item, find its parent and its other siblings
            $query .= "                
                WHERE (
                    `pastoral`.`parent_id` = :parent_id_0 OR 
                    `pastoral`.`id` = :parent_id_1 OR 
                    `pastoral`.`parent_id` = :id_0
                ) AND
                `pastoral`.deleted_at IS NULL
            ";

            $bindings['id_0']        = $record->getId();
            $bindings['parent_id_0'] = $record->getParent()->getId();
            $bindings['parent_id_1'] = $record->getParent()->getId();
        } else {
            // This is a parent item, just find its children

            $query .= "
                WHERE   (
                            (`pastoral`.`parent_id` = :parent_id_0 AND `pastoral`.`student_id` <> :student_id) OR
                            (`pastoral`.`id` = :parent_id_1)
                        ) 
                        AND `pastoral`.`deleted_at` IS NULL
            ";

            $bindings['student_id'] = $record->getStudent()->getId();
            $bindings['parent_id_0'] = $record->getId();
            $bindings['parent_id_1'] = $record->getId();
        }

        $statement = $this->database->prepare($query);
        $statement->execute($bindings);

        // Return hydrated objects- but don't hydrate their children or we'll end up in a recursive loop

        return $this->hydrateSelectInternal($statement, false);
    }


    /**
     * Display the most recent records that are tagged for display on dashboard based on their severity
     *
     * @param int  $limit
     * @return Collection|Model[]
     * @throws MapperException
     */
    public function findRecentDashboard($limit = 5)
    {
        return
            $this->findByColumnList([
                'sv.dashboard' => 1,
            ], [
                    'order' => ['created_at' => 'DESC'],
                    'limit' => $limit,
                ]
            );
    }

    /**
     * Display the most recent records that are tagged for display on dashboard based on their severity
     *
     * @param PastoralTag $tag
     *
     * @return Collection
     */
    public function findByTag(PastoralTag $tag)
    {
        $sql = $this->getSelectString() . "
        
            JOIN    `pastoral_tags` ON `pastoral_tags`.`pastoral_id` = `pastoral`.`id` AND 
                    `pastoral_tags`.`tag_id` = :tagId
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
            
            `pastoral`.`student_id` = :linkedStudentId AND `pastoral`.`parent_id` IN (
                SELECT      `id` 
                FROM        `pastoral` `pastoralLinked` 
                WHERE       `pastoralLinked`.`student_id` = :studentId
            ) OR `pastoral`.`id` IN (
                SELECT      `parent_id` 
                FROM        `pastoral` `pastoralLinked` 
                WHERE       `pastoralLinked`.`student_id` = :studentId2
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
            'studentId' => $student->getId(),
            'studentId2' => $student->getId(),
            'linkedStudentId' => $linkedStudent->getId(),
        ];

        if (!empty($typeId)) {
            $params['typeId'] = $typeId;
        }

        $statement->execute($params);
        return $this->hydrateSelect($statement);
    }


    /**
     * Perform a search of pastoral records (currently searches the heading, body and comments)
     *
     * @param string $search
     * @param User   $user The user who is searching
     *
     * @return Collection|Model[]
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     */
    public function getSearchResults($search, User $user)
    {
        $search = trim($search);

        $words = [];

        /** @var IQuoter $database */
        $database = $this->getDatabase();

        foreach (explode(' ', $search) as $word) {
            if (strlen($word) >= Results::MIN_SEARCH_LENGTH) {
                $words[] = "%{$database->escapeForLike($word)}%";
            }
        }

        if (!count($words)) {
            // All search words were too short
            return $this->createCollection();
        }

        // --------------------------------------------------------------------------------------------------------
        // Not deleted pastoral records
        // --------------------------------------------------------------------------------------------------------

        $recordNotDeletedClause = $this->getPredicateBuilder()
                                       ->setParamPrefix('rec_nondel')
                                       ->isNull('pastoral.deleted_at')
                                       ->getWhere()[0];

        // --------------------------------------------------------------------------------------------------------
        // Pastoral record heading
        // --------------------------------------------------------------------------------------------------------

        $pbHeadingSearch     = $this->getPredicateBuilder()
                                    ->setParamPrefix('heading')
                                    ->likeAny('pastoral.heading', $words);
        $recordHeadingClause = $pbHeadingSearch->getWhere()[0];

        // --------------------------------------------------------------------------------------------------------
        // Public pastoral content
        // --------------------------------------------------------------------------------------------------------

        $pbPublicBodySearch     = $this->getPredicateBuilder()
                                       ->setParamPrefix('pub_body')
                                       ->likeAny('pastoral.body_public', $words);
        $recordPublicBodyClause = $pbPublicBodySearch->getWhere()[0];

        // --------------------------------------------------------------------------------------------------------
        // Private pastoral content
        // --------------------------------------------------------------------------------------------------------

        $pbPrivateBodySearch     = $this->getPredicateBuilder()
                                        ->setParamPrefix('pri_body')
                                        ->likeAny('pastoral.body_private', $words);
        $recordPrivateBodyClause = $pbPrivateBodySearch->getWhere()[0];

        // --------------------------------------------------------------------------------------------------------
        // Not deleted comments
        // --------------------------------------------------------------------------------------------------------

        $commentNotDeletedClause = $this->getPredicateBuilder()
                                        ->setParamPrefix('com_nondel')
                                        ->isNull('pastoral_comment.deleted_at')
                                        ->getWhere()[0];

        // --------------------------------------------------------------------------------------------------------
        // Public comments
        // --------------------------------------------------------------------------------------------------------

        $pbPublicCommentSearch = $this->getPredicateBuilder()
                                      ->setParamPrefix('pub_cs')
                                      ->likeAny('pastoral_comment.body', $words);

        $commentPublicClause = implode(' OR ', $pbPublicCommentSearch->getWhere());

        $pbPublicCommentNotPrivateSearch = $this->getPredicateBuilder()
                                                ->setParamPrefix('pub_ispub')
                                                ->isFalse('pastoral_comment.private');

        $commentPublicClause = "
            (
                {$commentPublicClause} AND 
                {$pbPublicCommentNotPrivateSearch->getWhere()[0]} AND 
                {$commentNotDeletedClause}
            )
        ";

        // --------------------------------------------------------------------------------------------------------
        // Private comments
        // --------------------------------------------------------------------------------------------------------

        $pbPrivateCommentSearch = $this->getPredicateBuilder()
                                       ->setParamPrefix('pri_cs')
                                       ->likeAny('pastoral_comment.body', $words);

        $commentPrivateClause = implode(' OR ', $pbPrivateCommentSearch->getWhere());

        $pbPrivateCommentIsPrivateSearch = $this->getPredicateBuilder()
                                                ->setParamPrefix('pri_ispri')
                                                ->isTrue('pastoral_comment.private');

        $commentPrivateClause = "
            (
                {$commentPrivateClause} AND 
                {$pbPrivateCommentIsPrivateSearch->getWhere()[0]} AND 
                {$commentNotDeletedClause}
            )
        ";

        // --------------------------------------------------------------------------------------------------------
        // Is the owner
        // --------------------------------------------------------------------------------------------------------

        $pbOwnerSearch     = $this->getPredicateBuilder()
                                  ->setParamPrefix('os')
                                  ->equals('pastoral.owner_id', $user->getId());
        $recordOwnerClause = $pbOwnerSearch->getWhere()[0];

        // --------------------------------------------------------------------------------------------------------
        // Pastoral group access
        // --------------------------------------------------------------------------------------------------------

        $pbPublicGroupSearch = $this->getPredicateBuilder()
                                    ->setParamPrefix('pub_gs')
                                    ->greaterThanOrEqualTo('pastoral_access.access', PastoralAccessMapper::ACCESS_PUBLIC)
                                    ->notNull('pastoral_access.group_id')
                                    ->isNull('pastoral_access.deleted_at');

        $recordPublicGroupClause = implode(' AND ', $pbPublicGroupSearch->getWhere());

        $pbPrivateGroupSearch = $this->getPredicateBuilder()
                                     ->setParamPrefix('pri_gs')
                                     ->greaterThanOrEqualTo('pastoral_access.access', PastoralAccessMapper::ACCESS_PRIVATE)
                                     ->notNull('pastoral_access.group_id')
                                     ->isNull('pastoral_access.deleted_at');

        $recordPrivateGroupClause = implode(' AND ', $pbPrivateGroupSearch->getWhere());

        $pbGroupMemberSearch = $this->getPredicateBuilder()
                                    ->setParamPrefix('gms')
                                    ->equals('pastoral_group_member.user_id', $user->getId())
                                    ->isNull('pastoral_group_member.deleted_at');

        $recordGroupMemberClause = implode(' AND ', $pbGroupMemberSearch->getWhere());

        // --------------------------------------------------------------------------------------------------------
        // Pastoral role access
        // --------------------------------------------------------------------------------------------------------

        $pbPublicRoleSearch = $this->getPredicateBuilder()
                                   ->setParamPrefix('pub_rs')
                                   ->greaterThanOrEqualTo('pastoral_access.access', PastoralAccessMapper::ACCESS_PUBLIC)
                                   ->equals('pastoral_access.role_id', $user->getRole()->getId())
                                   ->isNull('pastoral_access.deleted_at');

        $recordPublicRoleClause = implode(' AND ', $pbPublicRoleSearch->getWhere());

        $pbPrivateRoleSearch = $this->getPredicateBuilder()
                                    ->setParamPrefix('pri_rs')
                                    ->greaterThanOrEqualTo('pastoral_access.access', PastoralAccessMapper::ACCESS_PRIVATE)
                                    ->equals('pastoral_access.role_id', $user->getRole()->getId())
                                    ->isNull('pastoral_access.deleted_at');

        $recordPrivateRoleClause = implode(' AND ', $pbPrivateRoleSearch->getWhere());

        // --------------------------------------------------------------------------------------------------------
        // Is explicitly allowed via individual user access
        // --------------------------------------------------------------------------------------------------------

        $pbPublicUserSearch = $this->getPredicateBuilder()
                                   ->setParamPrefix('pub_us')
                                   ->greaterThanOrEqualTo('pastoral_access.access', PastoralAccessMapper::ACCESS_PUBLIC)
                                   ->equals('pastoral_access.user_id', $user->getId())
                                   ->isNull('pastoral_access.deleted_at');

        $recordPublicUserClause = implode(' AND ', $pbPublicUserSearch->getWhere());

        $pbPrivateUserSearch = $this->getPredicateBuilder()
                                    ->setParamPrefix('pri_us')
                                    ->greaterThanOrEqualTo('pastoral_access.access', PastoralAccessMapper::ACCESS_PRIVATE)
                                    ->equals('pastoral_access.user_id', $user->getId())
                                    ->isNull('pastoral_access.deleted_at');

        $recordPrivateUserClause = implode(' AND ', $pbPrivateUserSearch->getWhere());

        // --------------------------------------------------------------------------------------------------------
        // Public access query amalgamation
        // --------------------------------------------------------------------------------------------------------

        $hasPublicAccess = "
            (
                `pastoral`.`id` IN (
                    SELECT  `pastoral_id` 
                    FROM    `pastoral_access` 
                    WHERE   {$recordPublicGroupClause} AND
                            `pastoral_access`.`group_id` IN (
                                SELECT  `group_id`
                                FROM    `pastoral_group_member`
                                WHERE   {$recordGroupMemberClause}
                            ) 
                ) OR
                `pastoral`.`id` IN (
                    SELECT  `pastoral_id` 
                    FROM    `pastoral_access` 
                    WHERE   {$recordPublicRoleClause}
                ) OR
                `pastoral`.`id` IN (
                    SELECT  `pastoral_id` 
                    FROM    `pastoral_access` 
                    WHERE   {$recordPublicUserClause}
                ) OR
                {$recordOwnerClause}                    
            )
        ";

        // --------------------------------------------------------------------------------------------------------
        // Private access query amalgamation
        // --------------------------------------------------------------------------------------------------------

        $hasPrivateAccess = "
            (
                `pastoral`.`id` IN (
                    SELECT  `pastoral_id` 
                    FROM    `pastoral_access` 
                    WHERE   {$recordPrivateGroupClause} AND
                            `pastoral_access`.`group_id` IN (
                                SELECT  `group_id`
                                FROM    `pastoral_group_member`
                                WHERE   {$recordGroupMemberClause}
                            ) 
                ) OR
                `pastoral`.`id` IN (
                    SELECT  `pastoral_id` 
                    FROM    `pastoral_access` 
                    WHERE   {$recordPrivateRoleClause}
                ) OR
                `pastoral`.`id` IN (
                    SELECT  `pastoral_id` 
                    FROM    `pastoral_access` 
                    WHERE   {$recordPrivateUserClause}
                ) OR
                {$recordOwnerClause}               
            )
        ";

        // --------------------------------------------------------------------------------------------------------
        // Construct the final query
        // --------------------------------------------------------------------------------------------------------

        $sql = $this->getSelectString() . "
            LEFT JOIN `pastoral_comment` ON `pastoral_comment`.`pastoral_id` = `pastoral`.`id`
            WHERE 
            (
                ({$recordNotDeletedClause}) AND
                (
                    (
                        ({$recordHeadingClause} OR {$recordPublicBodyClause} OR {$commentPublicClause}) AND 
                        ({$hasPublicAccess})
                    ) 
                    OR 
                    (
                        ({$recordHeadingClause} OR {$recordPrivateBodyClause} OR {$commentPrivateClause}) AND 
                        ({$hasPrivateAccess})
                    ) 
                )
            )
        ";

        // --------------------------------------------------------------------------------------------------------
        // Bind the necessary parameters and get this show on the road
        // --------------------------------------------------------------------------------------------------------

        $parameters = array_merge(
            $pbHeadingSearch->getParams(),
            $pbPublicBodySearch->getParams(),
            $pbPrivateBodySearch->getParams(),
            $pbPublicCommentSearch->getParams(),
            $pbPublicCommentNotPrivateSearch->getParams(),
            $pbPrivateCommentSearch->getParams(),
            $pbPrivateCommentIsPrivateSearch->getParams(),
            $pbOwnerSearch->getParams(),
            $pbPublicGroupSearch->getParams(),
            $pbPrivateGroupSearch->getParams(),
            $pbGroupMemberSearch->getParams(),
            $pbPublicRoleSearch->getParams(),
            $pbPrivateRoleSearch->getParams(),
            $pbPublicUserSearch->getParams(),
            $pbPrivateUserSearch->getParams()
        );

        $statement = $this->database->prepare($sql);
        $statement->execute($parameters);

        // --------------------------------------------------------------------------------------------------------
        // Return the search results
        // --------------------------------------------------------------------------------------------------------
        return $this->hydrateSelect($statement);
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
     * @param Collection            $types,
     * @param Collection            $subtypes,
     * @param Collection            $authors
     * @param Collection            $students
     * @param Collection            $tags
     * @param Collection            $order
     * @param bool                  $involved
     * @param Collection            $years
     * @param Collection            $houses
     *
     * @return Collection|Model[]
     */
    public function getReportData(
        User $user,
        Alaress_Date_Format $before_date,
        Alaress_Date_Format $after_date,
        ?PastoralSeverity $severity,
        Collection $types,
        Collection $subtypes,
        Collection $authors,
        Collection $students,
        Collection $tags,
        Collection $order,
        $involved,
        Collection $years,
        Collection $houses
    ) {
        $this->bindings = [];
        $this->filters  = [];

        $orderSql = '';

        if ($before_date instanceof Alaress_Date_Format && !$before_date instanceof Alaress_Date_NullDate) {
            $this->bindings[':beforeDate'] = $before_date->endOfDay()->toIso8601String();
            $this->filters[]               = '`pastoral`.`created_at` <= :beforeDate';
        }

        if ($after_date instanceof Alaress_Date_Format && !$after_date instanceof Alaress_Date_NullDate) {
            $this->bindings[':afterDate'] = $after_date->startOfDay()->toIso8601String();
            $this->filters[]              = '`pastoral`.`created_at` >= :afterDate';
        }

        if (!empty($severity) && $this->getConfigLoader()->get('pastoral_severity')) {
            $this->bindings[':severity'] = $severity->getId();
            $this->filters[]             = '`sv`.`order` >= :severity';
        }

        $this->addFilter($students, '`student_id`');
        $this->addFilter($years, '`s`.`year`');
        $this->addFilter($houses, '`s`.`house`');
        $this->addFilter($types, '`type_id`');
        $this->addFilter($subtypes, '`subtype_id`');
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
                case 'type':
                    $orderParts[] = "`t`.name";
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
                case 'house':
                    $orderParts[] = "`s`.`house`";
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
                    (SELECT COUNT(*) FROM `pastoral_access` WHERE `role_id` = :user_role_id_0 AND `pastoral_id` = `pastoral`.`id`) > 0 OR
                    (SELECT COUNT(*) FROM `pastoral_access` WHERE `user_id` = :user_id_0      AND `pastoral_id` = `pastoral`.`id`) > 0 OR
                    (
                        SELECT COUNT(*) FROM `pastoral_access` WHERE `group_id` IN (
                            SELECT `group_id`
                            FROM `pastoral_group_member`
                            WHERE `user_id` = :user_id_1
                        ) AND `pastoral_id` = `pastoral`.`id`
                    ) > 0 OR
                    `pastoral`.`owner_id` = :user_id_2
                )
            ";
            $this->bindings['user_role_id_0'] = $user->getRole()->getId();
            $this->bindings['user_id_0']      = $user->getId();
            $this->bindings['user_id_1']      = $user->getId();
            $this->bindings['user_id_2']      = $user->getId();
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
}
