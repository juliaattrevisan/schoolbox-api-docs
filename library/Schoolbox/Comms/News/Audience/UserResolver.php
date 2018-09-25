<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Comms\News\Audience;

use Alaress\Database;
use Schoolbox\Permission\Access\UserAccess;
use External_ClassInterface;
use PDO;
use UnexpectedValueException;

/**
 * Responsible for:
 * * Given an article, finding the set of users who are in its audience.
 */
class UserResolver implements IUserResolver
{

    /** @var BusinessRules */
    private $businessRules;

    /** @var Database\Connector\IConnector */
    private $db;

    /** @var External_ClassInterface */
    private $externalClass;

    /** @var RelationshipFilterer */
    private $relationshipFilterer;

    /** @var Mapper */
    private $audienceMapper;


    /**
     * @param BusinessRules                 $businessRules
     * @param Database\Connector\IConnector $db
     * @param External_ClassInterface       $externalClass
     * @param RelationshipFilterer          $relationshipFilterer
     * @param Mapper                        $audienceMapper
     */
    public function __construct(
        BusinessRules $businessRules,
        Database\Connector\IConnector $db,
        External_ClassInterface $externalClass,
        RelationshipFilterer $relationshipFilterer,
        Mapper $audienceMapper
    ) {
        $this->businessRules        = $businessRules;
        $this->db                   = $db;
        $this->externalClass        = $externalClass;
        $this->relationshipFilterer = $relationshipFilterer;
        $this->audienceMapper       = $audienceMapper;
    }


    /**
     * Returns the ids of users who are in the article's audience
     *
     * @param \Schoolbox\Comms\News\Model $article
     * @return int[]
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws UnexpectedValueException If we attempt to build a query with an unrecognised entity type
     * @throws \UnderflowException
     * @TODO This is too poorly implemented and needs to be optimised. See SA-7431
     */
    public function findUsersForArticle(\Schoolbox\Comms\News\Model $article): array
    {
        $userIds = [];
        $segments = $this->audienceMapper->findByColumn(Mapper::FIELD_COMMS_NEWS_ARTICLE_ID, $article->getId());

        foreach ($segments as $rootAudienceSegment) {
            // Root level segments are always a UNION
            /** @noinspection SlowArrayOperationsInLoopInspection */
            $userIds = \array_merge($userIds, $this->findUsersForAudienceSegment($rootAudienceSegment));
        }
        return \array_unique($userIds);
    }


    /**
     * Returns the ids of users in an audience segment
     *
     * @param Model $audienceSegment
     * @return int[]
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws UnexpectedValueException If we attempt to build a query with an unrecognised entity type
     * @throws \UnderflowException
     */
    private function findUsersForAudienceSegment(Model $audienceSegment): array
    {
        $entityType = $audienceSegment->getEntityType();

        // Simple base case:
        if (Model::ENTITY_TYPE_COMPOSITE !== $entityType) {
            $userIds = $this->getUserIds($audienceSegment->getEntityType(), $audienceSegment->getEntityValue());
            return $this->applyRelationshipFilter($userIds, $audienceSegment->getRelationship());
        }

        // Recursive composite case:
        $pb = $this->audienceMapper->getPredicateBuilder()
                                   ->equals(Mapper::FIELD_PARENT_ID, $audienceSegment->getId());

        $aggregateUsers = [];
        $firstRow       = true;
        foreach ($this->audienceMapper->findByPredicates($pb) as $childSegment) {
            if ($firstRow) {
                $aggregateUsers = $this->findUsersForAudienceSegment($childSegment);
                $firstRow       = false;
            } else {
                $aggregateUsers = \array_intersect($aggregateUsers, $this->findUsersForAudienceSegment($childSegment));
            }
        }

        return $this->applyRelationshipFilter($aggregateUsers, $audienceSegment->getRelationship());
    }


    /**
     * Returns the set of users who are related to the specified users by the given relationship filter
     *
     * @param array  $userIds
     * @param string $relationship ['self', 'parents_of', 'teachers_of']
     * @return int[]
     * @throws \UnderflowException
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws UnexpectedValueException If an unrecognised relationship modifier is encountered
     */
    private function applyRelationshipFilter(array $userIds, string $relationship): array
    {
        if (!\count($userIds)) {
            return [];
        }
        if (Model::RELATIONSHIP_SELF === $relationship) {
            return $userIds;
        }

        switch ($relationship) {
            case Model::RELATIONSHIP_PARENTS_OF:
                return $this->relationshipFilterer->getParents($userIds);
            case Model::RELATIONSHIP_TEACHERS_OF:
                return $this->relationshipFilterer->getTeachersAndInstructors($userIds);
            default:
                throw new UnexpectedValueException("Unrecognised relationship modifier: {$relationship}");
        }
    }


    /**
     * Returns a set of userIds that match a given entity type and identifier
     *
     * @param string      $entityType
     * @param null|string $entityValue
     * @return int[]
     * @throws UnexpectedValueException If an unrecognised entity type is passed
     * @throws Database\Predicate\InvalidColumnException
     * @throws \UnderflowException
     */
    private function getUserIds(string $entityType, ?string $entityValue): array
    {
        if (null === $entityValue) {
            // Is null a valid segment entity value?
            if (!$this->businessRules->canTargetNullEntity($entityType)) {
                return [];
            }
            $predicate  = 'IS NULL';
            $parameters = null;
        } else {
            $predicate  = '= :entity_value';
            $parameters = ['entity_value' => $entityValue];
        }

        // TODO This logic is tightly coupled to ArticleResolver::addClauses(). Snap each out into class behind interface (Segment\IStrategy) so coupled
        // TODO  pairs have their implementations inside a single class in adjacent methods.

        switch ($entityType) {
            case Model::ENTITY_TYPE_USER:
                return $this->db->executeQuery("SELECT u.id FROM user u WHERE u.id {$predicate} AND u.deleted_at IS NULL", $parameters)
                                ->fetchAll(PDO::FETCH_COLUMN);
            case Model::ENTITY_TYPE_SUPER:
                return $this->db->executeQuery('SELECT u.id FROM user u JOIN superusers su ON u.id = su.id AND u.deleted_at IS NULL', $parameters)
                                ->fetchAll(PDO::FETCH_COLUMN);
            case Model::ENTITY_TYPE_CAMPUS:
                return $this->db->executeQuery("SELECT u.id FROM user u JOIN userCampus uc ON u.id = uc.uid WHERE uc.campus {$predicate} AND u.deleted_at IS NULL", $parameters)
                                ->fetchAll(PDO::FETCH_COLUMN);
            case Model::ENTITY_TYPE_ROLE:
                return $this->db->executeQuery("SELECT u.id FROM user u WHERE u.group_id {$predicate} AND u.deleted_at IS NULL", $parameters)
                                ->fetchAll(PDO::FETCH_COLUMN);
            case Model::ENTITY_TYPE_ROLE_TYPE:
                return $this->db->executeQuery("SELECT u.id FROM user u JOIN groups g ON u.group_id = g.id WHERE g.group_type {$predicate} AND u.deleted_at IS NULL", $parameters)
                                ->fetchAll(PDO::FETCH_COLUMN);
            case Model::ENTITY_TYPE_SCHOOL_HOUSE:
                return $this->db->executeQuery("SELECT u.id FROM user u WHERE u.house {$predicate} AND u.deleted_at IS NULL", $parameters)
                                ->fetchAll(PDO::FETCH_COLUMN);
            case Model::ENTITY_TYPE_RES_HOUSE:
                return $this->db->executeQuery("SELECT u.id FROM user u WHERE u.residential_house_id {$predicate} AND u.deleted_at IS NULL", $parameters)
                                ->fetchAll(PDO::FETCH_COLUMN);
            case Model::ENTITY_TYPE_YEAR:
                return $this->db->executeQuery("SELECT u.id FROM user u WHERE u.year {$predicate} AND u.deleted_at IS NULL", $parameters)
                                ->fetchAll(PDO::FETCH_COLUMN);
            case Model::ENTITY_TYPE_SERVICE:
                if (null === $entityValue) {
                    return $this->db->executeQuery("SELECT u.id FROM user u LEFT JOIN userAccess ua ON u.id = ua.uid WHERE ua.uid {$predicate} AND u.deleted_at IS NULL", $parameters)
                                    ->fetchAll(PDO::FETCH_COLUMN);
                }
                [$service, $sid, $type] = \explode('.', $entityValue);
                $whereClauses = [];
                $parameters   = [];
                if ('*' !== $service) {
                    $whereClauses[]        = 'ua.service = :service';
                    $parameters['service'] = $service;
                }
                if ('*' !== $sid) {
                    $whereClauses[]    = 'ua.sid = :sid';
                    $parameters['sid'] = $sid;
                }
                if ('*' !== $type) {
                    $whereClauses[]     = 'ua.type = :type';
                    $parameters['type'] = $type;
                }
                $whereClause = \count($whereClauses) ? \implode(' AND ', $whereClauses) : 'TRUE';
                return $this->db->executeQuery("SELECT u.id FROM user u JOIN userAccess ua ON u.id = ua.uid WHERE {$whereClause} AND u.deleted_at IS NULL", $parameters)
                                ->fetchAll(PDO::FETCH_COLUMN);
            case Model::ENTITY_TYPE_PASTORAL_GROUP:
                return $this->db->executeQuery("SELECT u.id FROM user u LEFT JOIN pastoral_group_member pgm ON pgm.user_id = u.id WHERE pgm.group_id {$predicate} AND u.deleted_at IS NULL", $parameters)
                                ->fetchAll(PDO::FETCH_COLUMN);
            case Model::ENTITY_TYPE_FOLDER:
                // Get group users, regardless of whether they have read/write/mod perms. (but not deny)
                $innerPbGroup = (new Database\Predicate\Builder($this->db, 'user'))
                    ->equals('ua.service', UserAccess::SERVICE_HOMEPAGE)
                    ->equals('ua.sid', $entityValue)
                    ->notEquals('ua.type', UserAccess::ACCESS_DENY);
                $outerPb      = (new Database\Predicate\Builder($this->db, 'user'))
                    ->all($innerPbGroup);

                // Associated with the class, regardless of role type.
                // For students/teachers you'd need to use a composite segment that intersects the desired roleType.
                $codes = $this->db->executeQuery(
                    "SELECT code FROM folder_code fc JOIN folder f ON fc.folder_id=f.id AND fc.deleted_at IS NULL AND f.deleted_at IS NULL WHERE f.id= :folder_id",
                    ['folder_id' => $entityValue])->fetchAll(PDO::FETCH_COLUMN);
                $externalIds = [];
                foreach ($this->externalClass->getStudents(implode(',', $codes)) as $student) {
                    $externalIds[] = $student['synergy_id'];
                }
                foreach ($this->externalClass->getTeacherName(implode(',', $codes)) as $teacherExternalId) {
                    $externalIds[] = $teacherExternalId;
                }
                if (\count($externalIds)) {
                    $outerPb->in('u.synergy_id', $externalIds);
                }

                $whereClause = \implode(' OR ', $outerPb->getWhere());
                $sql         = "SELECT u.id FROM user u JOIN userAccess ua on u.id = ua.uid WHERE {$whereClause}  AND u.deleted_at IS NULL";
                return $this->db->executeQuery($sql, $outerPb->getParams())->fetchAll(PDO::FETCH_COLUMN);
            default:
                throw new UnexpectedValueException("Entity type not recognised: {$entityType}");
        }
    }
}
