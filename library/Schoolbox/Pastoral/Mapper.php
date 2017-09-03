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
use External_Pastoral;
use PDO;
use PDOStatement;
use Schoolbox\Collection;
use Schoolbox\Mapper as BaseMapper;
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
    /** @var array Used in report builder only */
    private $filters = [];

    /** @var array Used in report builder only */
    private $bindings = [];

    /**
     * @inheritdoc
     */
    protected function mapping()
    {
        $this->hasTable('pastoral');
        $this->hasModel(Model::class);
        $this->joins('parent_id', self::class, 'id', null, null, 'parent', 'parent');
        $this->joins('type_id', Type\Mapper::class, 'id', null, null, 'type', 'type');
        $this->joins('category_id', Category\Mapper::class, 'id', null, null, 'category', 'category');
        $this->joins('severity_id', Severity\Mapper::class, 'id', null, null, 'severity', 'severity');
        $this->joins('owner_id', UserMapper::class, 'id', null, null, 'owner', 'owner');
        $this->joins('modified_by_user_id', UserMapper::class, 'id', null, null, 'modifiedBy', 'modifiedBy');
        $this->joins('student_id', UserMapper::class, 'id', null, null, 'student', 'student');
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('external_id', self::TYPE_INTEGER);
        $this->uses('body', self::TYPE_STRING);
        $this->uses('heading', self::TYPE_STRING);
        $this->uses('points', self::TYPE_DECIMAL);
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);

        $this->joinsReverseMulti('pastoral_id', Access\Mapper::class, 'access', 'access');
        $this->joinsReverseMulti('pastoral_id', Comment\Mapper::class, 'comments', 'comments');
        $this->joinsReverseMulti('pastoral_id', Attachment\Mapper::class, 'attachments', 'attachments');

        $this->joinsReverseMulti('pastoral_id', Tag\Mapper::class, 'tags', 'tags');
        $this->joinsReverseMulti('pastoral_id', Revision\Mapper::class, 'revisions', 'revisions');

        $this->registerCallback(Mapper::CALLBACK_POSTSAVE, [$this, 'saveRevision']);
        $this->registerCallback(Mapper::CALLBACK_PREDELETE, [$this, 'deleteReferences']);
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

        $pastoralTagMapper = Tag\Mapper::getInstance();
        $pastoralTagMapper->deleteForRecord($model);
        $pastoralTagMapper->purgeUnassociated();
    }

    /**
     * Prior to saving changes to a record, we cover out butts and keep a backup in the revision table for posterity
     *
     * @param Model $model
     */
    public function saveRevision(Model $model)
    {
        // Only save the revision if we are updating an existing record

        $pastoralRevisionMapper = Revision\Mapper::getInstance();
        $pastoralRevision = $pastoralRevisionMapper->createRevision($model);
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

        return
            "
            SELECT      {$this->getColumnString()},
                        {$this->getJoinColumnString('student', 's')},
                        {$this->getJoinColumnString('severity', 'sv')},
                        {$this->getJoinColumnString('owner', 'o')},
                        {$this->getJoinColumnString('type', 't')},
                        {$this->getJoinColumnString('category', 'c')}
            FROM        {$this->getTable()}
            INNER JOIN   {$this->getJoinSql('student', '', 's', true)}
            LEFT  JOIN   {$this->getJoinSql('severity', '', 'sv')}
            INNER JOIN   {$this->getJoinSql('owner', '', 'o', true)}
            INNER JOIN   {$this->getJoinSql('type', '', 't', true)}
            LEFT  JOIN   {$this->getJoinSql('category', '', 'c')}
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
     * This is also used by the PastoralRevision class to hydrate a record
     *
     * @param $row
     * @param $hydrateRelatedRecords
     * @return Model|bool
     * @throws BaseMapper\DefinitionException If a specified mapper is not found in definition
     * @throws \Schoolbox\Mapper\Exception If unable to hydrate a row
     */
    protected function hydrateRow($row, $hydrateRelatedRecords)
    {

        // This method gets called before checking authorization- so if the session timed out this might be blank
        if (!$this->getSessionUser() instanceof User) {
            return false;
        }

        /** @var UserMapper $ownerMapper */
        $ownerMapper = $this->getJoinMapper('owner');

        /** @var UserMapper $studentMapper */
        $studentMapper = $this->getJoinMapper('student');

        /** @var Severity\Mapper $severityMapper */
        $severityMapper = $this->getJoinMapper('severity');

        /** @var Type\Mapper $typeMapper */
        $typeMapper = $this->getJoinMapper('type');

        /** @var Category\Mapper $categoryMapper */
        $categoryMapper = $this->getJoinMapper('category');

        $model = $this->hydrate($row);
        $model->setOwner($ownerMapper->hydrate($row, 'o'));

        if ($this->getConfigLoader()->get('pastoral_severity')) {
            $model->setSeverity($severityMapper->hydrate($row, 'sv'));
        }

        $model->setType($typeMapper->hydrate($row, 't'));
        $model->setCategory($categoryMapper->hydrate($row, 'c'));
        $model->setStudent($studentMapper->hydrate($row, 's')->loadParents()->loadTeachers());
        $model->setAccess($this->getJoinMapper('access')->findByColumn('pastoral_id', $model->getId()));

        // If the user is not in the audience- and is not the owner, they can't see the record
        $inAudience = $this->isUserInPastoralService($this->getSessionUser());

        if (!$inAudience) {
            foreach ($model->getAccess() as $access) {
                if ($access->hasAccess($this->getSessionUser())) {
                    $inAudience = true;
                    break;
                }
            }
        }

        if (!$inAudience && $model->getOwner()->getId() !== $this->getSessionUser()->getId()) {
            return false;
        }

        $model->setTags(Tag\Mapper::getInstance()->findForRecord($model));
        $model->setComments($this->getJoinMapper('comments')->findByColumn('pastoral_id', $model->getId()));
        $model->setAttachments($this->getJoinMapper('attachments')->findByColumn('pastoral_id', $model->getId()));

        // Only hydrate related records if we're specifically told to
        if ($hydrateRelatedRecords) {
            $model->setRelatedRecords($this->getRelatedRecords($model));
        }

        // Return hydrated model
        return $model;
    }

    /**
     * Return any records immediately related to the supplied pastoral record
     *
     * @param Model $record
     * @return Collection
     */
    public function getRelatedRecords(Model $record)
    {
        $query = $this->getSelectString();

        if ($record->getParent() instanceof Model) {

            // This is a child item, find its parent and its other siblings

            if ($record instanceof Revision\Model) {
                $query .= "                
                    WHERE (
                        `pastoral_revision`.`parent_id` = :parent_id_0 OR 
                        `pastoral_revision`.`id` = :parent_id_1 OR 
                        `pastoral_revision`.`parent_id` = :id_0
                    ) AND
                       `pastoral_revision`.`id` <> :id_1
                     AND `pastoral_revision`.deleted_at IS NULL
                ";
            } else {
                $query .= "                
                    WHERE (
                        `pastoral`.`parent_id` = :parent_id_0 OR 
                        `pastoral`.`id` = :parent_id_1 OR 
                        `pastoral`.`parent_id` = :id_0
                    ) AND
                        `pastoral`.`id` <> :id_1
                    AND `pastoral`.deleted_at IS NULL
                ";
            }

            $bindings['id_0'] = $record->getId();
            $bindings['id_1'] = $record->getId();
            $bindings['parent_id_0'] = $record->getParent()->getId();
            $bindings['parent_id_1'] = $record->getParent()->getId();

        } else {

            // This is a parent item, just find its children

            $query .= "WHERE `parent_id` = :parent_id AND pastoral.deleted_at IS NULL";
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
     * @param int $count Number of recent records to display
     * @return Collection|Model[]
     */
    public function findRecentDashboard($count = 5)
    {
        $records = $this->findByColumnList([
            'sv.dashboard' => 1,
        ], [
            'limit' => $count,
            ['order' => ['created_at' => 'DESC']],
        ]);

        $displayed = $this->createCollection();

        /** @var Model $record */
        foreach ($records as $record) {
            if ($record->getOwner()->getId() == $this->getSessionUser()->getId()) {
                $displayed->add($record);
                continue;
            }

            /** @var Access\Model $access */
            foreach ($record->getAccess() as $access) {
                if ($access->hasAccess($this->getSessionUser())) {
                    $displayed->add($record);
                    break;
                }
            }
        }

        return $displayed;
    }

    /**
     * Display the most recent records that are tagged for display on dashboard based on their severity
     *
     * @param Tag\Model $tag
     * @return Collection
     */
    public function findByTag($tag)
    {
        $tagId = $this->getDatabase()->quote($tagId = $tag->getId());

        $sql = $this->getSelectString();

        $sql .= "
        
            JOIN `pastoral_tags` ON 
                `pastoral_tags`.`pastoral_id` = `pastoral`.`id` AND 
                `pastoral_tags`.`tag_id` = {$tagId} AND
                `pastoral_tags`.`deleted_at` IS NULL
        ";

        $statement = $this->database->prepare($sql);
        $statement->execute();

        return $this->hydrateSelect($statement);
    }

    /**
     * Display the most recent records that are tagged for display on dashboard based on their severity
     *
     * @param User     $student
     * @param User     $linkedStudent
     * @param int|null $typeId
     * @return Collection
     */
    public function findLinked($student, $linkedStudent, $typeId = null)
    {
        $studentId = $this->getDatabase()->quote($student->getId());
        $linkedStudentId = $this->getDatabase()->quote($linkedStudent->getId());

        $sql = $this->getSelectString();

        $sql .= "
            WHERE `pastoral`.`student_id` = {$studentId} AND `pastoral`.`id` IN (
                SELECT      `parent_id` 
                FROM        `pastoral` `pastoralLinked` 
                WHERE       `pastoralLinked`.`student_id` = {$linkedStudentId}
            )
        ";

        if (!empty($typeId)) {
            $sql .= " AND type_id = :typeId";
        }

        $sql .= "
            ORDER BY `pastoral`.`created_at` DESC
        ";

        $statement = $this->database->prepare($sql);

        if (!empty($typeId)) {
            $statement->bindValue('typeId', $typeId);
        }

        $statement->execute();

        $results = $this->hydrateSelect($statement);

        return $results;
    }

    /**
     * Perform a search of pastoral records (currently searches the heading, body and comments)
     *
     * @param string $search
     * @return Collection|Model[]
     */
    public function getSearchResults($search)
    {
        $search = trim($search);

        // Keep an audit trail of searches
        $pastoralAuditMapper = Audit\Mapper::getInstance();
        $auditRecord = $pastoralAuditMapper->createModel();
        $auditRecord->setUser($this->getSessionUser());
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
            // Search heading & body of pastoral record
            $pbRecordSearch = $this->getPredicateBuilder();
            $pbRecordSearch->like('pastoral.body', $words);
            $pbRecordSearch->like('pastoral.heading', $words);
            $recordBodyAndHeadingClause = implode(' OR ', $pbRecordSearch->getWhere());

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
            WHERE (
                ($recordBodyAndHeadingClause)
                OR
                (pastoral.id IN (SELECT pastoral_id FROM pastoral_comment WHERE ($commentBodyClause) AND $commentDeleteClause))
            )
            AND $recordDeletionClause";

            // Bind the parameters
            $statement = $this->database->prepare($sql);

            // Retrieve the hydrated records
            $statement->execute(array_merge($pbRecordSearch->getParams(), $pbCommentSearch->getParams()));

            $results = $this->hydrateSelect($statement);

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
     * @var int $placeholderIndex This is a static placeholder used to make sure parameters in report filters are given
     *      unique names
     */
    private static $placeholderIndex = 0;

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

            $this->bindings = array_merge($this->bindings, $pbArray['bindings']);
            $this->filters[] = "{$field} IN ({$pbArray['placeholders']})";
        }

        self::$placeholderIndex++;
    }

    /**
     * This is the function that gets the report results out of the database
     *
     * @param User                $user
     * @param Alaress_Date_Format $before_date
     * @param Alaress_Date_Format $after_date
     * @param null|Severity\Model $severity
     * @param Collection $types
     * @param Collection $authors
     * @param Collection $students
     * @param Collection $tags
     * @param Collection $order
     * @param bool $involved
     * @param Collection $years
     *
     * @return Collection|Model[]
     */
    public function getReportData(
        User $user,
        Alaress_Date_Format $before_date,
        Alaress_Date_Format $after_date,
        Severity\Model $severity = null,
        Collection $types,
        Collection $authors,
        Collection $students,
        Collection $tags,
        Collection $order,
        $involved,
        Collection $years
    ) {
        $this->bindings = [];
        $this->filters = [];

        $criteriaSql = '';
        $orderSql = '';

        if ($before_date instanceof Alaress_Date_Format && !$before_date instanceof Alaress_Date_NullDate) {
            $this->bindings[':beforeDate'] = $before_date->endOfDay()->toIso8601String();
            $this->filters[] = '`created_at` <= :beforeDate';
        }

        if ($after_date instanceof Alaress_Date_Format && !$after_date instanceof Alaress_Date_NullDate) {
            $this->bindings[':afterDate'] = $after_date->startOfDay()->toIso8601String();
            $this->filters[] = '`created_at` >= :afterDate';
        }

        if (!empty($severity) && $this->getConfigLoader()->get('pastoral_severity')) {
            $this->bindings[':severity'] = $severity->getId();
            $this->filters[] = '`sv`.`order` >= :severity';
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
                case 'category':
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

        if ($involved || !$this->isUserInPastoralService($this->getSessionUser())) {
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

        if (!empty($criteriaParts)) {
            $criteriaSql = ' WHERE ' . implode(' AND ', $criteriaParts);
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

        $pastoralTagMapper = Tag\Mapper::getInstance();
        $resultsFiltered = $this->createCollection();

        /** @var Model $result */
        foreach ($results as $result) {

            $found = false;
            $tagsRecord = $pastoralTagMapper->findForRecord($result);

            /** @var Tag\Model $tagFilter */
            foreach ($tags as $tagFilter) {

                /** @var Tag\Model $tagRecord */
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
     * @return bool
     */
    private function isUserInPastoralService(User $user)
    {
        return $user->getAccessList()->filterBy('service', UserAccess::SERVICE_PASTORAL)->count() > 0;
    }
}
