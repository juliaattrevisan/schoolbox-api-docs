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
use External_Class;
use PDO;
use UnexpectedValueException;

/**
 * Responsible for:
 * * Given an article, finding the set of users who are in its audience.
 */
class UserResolver
{

    /** @var Database\Connector\IConnector */
    private $db;

    /** @var \External_Class */
    private $externalClass;

    /** @var RelationshipFilterer */
    private $relationshipFilterer;

    /** @var Mapper */
    private $audienceMapper;


    /**
     * @param Database\Connector\IConnector $db
     * @param External_Class                $externalClass
     * @param RelationshipFilterer          $relationshipFilterer
     * @param Mapper                        $audienceMapper
     */
    public function __construct(
        Database\Connector\IConnector $db,
        External_Class $externalClass,
        RelationshipFilterer $relationshipFilterer,
        Mapper $audienceMapper
    ) {
        $this->db                   = $db;
        $this->externalClass        = $externalClass;
        $this->relationshipFilterer = $relationshipFilterer;
        $this->audienceMapper       = $audienceMapper;
    }


    /**
     * Returns the ids of users who are in the article's audience
     *
     * @param int $articleId
     * @return int[]
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws UnexpectedValueException If we attempt to build a query with an unrecognised entity type
     * @todo consider requiring a hydrated article, with all audience segments hydrated
     * @throws \UnderflowException
     */
    public function findUsersForArticle(int $articleId): array
    {
        $userIds = [];
        $pb      = $this->audienceMapper->getPredicateBuilder()->equals(Mapper::FIELD_COMMS_NEWS_ARTICLE_ID, $articleId);

        foreach ($this->audienceMapper->findByPredicates($pb) as $rootAudienceSegment) {
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
        foreach($this->audienceMapper->findByPredicates($pb) as $childSegment){
            if($firstRow){
                $aggregateUsers = $this->findUsersForAudienceSegment($childSegment);
                $firstRow = false;
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
     * @param string $entityType
     * @param null|string $entityValue
     * @return int[]
     * @throws UnexpectedValueException If an unrecognised entity type is passed
     */
    private function getUserIds(string $entityType, ?string $entityValue): array
    {
        if (null === $entityValue) {
            // Is null a valid segment entity value?
            if(!BusinessRules::canTargetNullEntity($entityType)){
                return [];
            }
            $predicate = 'IS NULL';
        } else {
            $predicate = "= '{$entityValue}'";
        }

        switch ($entityType) {
            case Model::ENTITY_TYPE_USER:
                return $this->db->executeQuery("SELECT u.id FROM user u WHERE u.id {$predicate} AND u.deleted_at IS NULL")->fetchAll(PDO::FETCH_COLUMN);
            case Model::ENTITY_TYPE_SUPER:
                return $this->db->executeQuery('SELECT u.id FROM user u JOIN superusers su ON u.id = su.id AND u.deleted_at IS NULL')->fetchAll(PDO::FETCH_COLUMN);
            case Model::ENTITY_TYPE_CAMPUS:
                return $this->db->executeQuery("SELECT u.id FROM user u JOIN userCampus uc ON u.id = uc.uid WHERE uc.campus {$predicate} AND u.deleted_at IS NULL")->fetchAll(PDO::FETCH_COLUMN);
            case Model::ENTITY_TYPE_ROLE:
                return $this->db->executeQuery("SELECT u.id FROM user u WHERE u.group_id {$predicate} AND u.deleted_at IS NULL")->fetchAll(PDO::FETCH_COLUMN);
            case Model::ENTITY_TYPE_ROLE_TYPE:
                return $this->db->executeQuery("SELECT u.id FROM user u JOIN groups g ON u.group_id = g.id WHERE g.group_type {$predicate} AND u.deleted_at IS NULL")->fetchAll(PDO::FETCH_COLUMN);
            case Model::ENTITY_TYPE_SCHOOL_HOUSE:
                return $this->db->executeQuery("SELECT u.id FROM user u WHERE u.house {$predicate} AND u.deleted_at IS NULL")->fetchAll(PDO::FETCH_COLUMN);
            case Model::ENTITY_TYPE_RES_HOUSE:
                return $this->db->executeQuery("SELECT u.id FROM user u WHERE u.residential_house_id {$predicate} AND u.deleted_at IS NULL")->fetchAll(PDO::FETCH_COLUMN);
            case Model::ENTITY_TYPE_YEAR:
                return $this->db->executeQuery("SELECT u.id FROM user u WHERE u.year {$predicate} AND u.deleted_at IS NULL")->fetchAll(PDO::FETCH_COLUMN);
            case Model::ENTITY_TYPE_SERVICE:
                [$service, $sid, $type] = \explode('.', $entityValue);
                $whereClauses = [];
                if('*' !== $service){
                    $whereClauses[] = "ua.service = $service";
                }
                if('*' !== $sid){
                    $whereClauses[] = "ua.sid = $sid";
                }
                if('*' !== $type){
                    $whereClauses[] = "ua.type = $type";
                }
                $whereClause = \count($whereClauses) ? \implode(' AND ', $whereClauses) : 'TRUE';
                return $this->db->executeQuery("SELECT u.id FROM user u JOIN userAccess ua ON u.id = ua.uid WHERE {$whereClause} AND u.deleted_at IS NULL")->fetchAll(PDO::FETCH_COLUMN);
            case Model::ENTITY_TYPE_PASTORAL_GROUP:
                return $this->db->executeQuery("SELECT u.id FROM user u LEFT JOIN pastoral_group_member pgm ON pgm.user_id = u.id WHERE pgm.group_id {$predicate} AND u.deleted_at IS NULL")->fetchAll(PDO::FETCH_COLUMN);
            case Model::ENTITY_TYPE_GROUP:
                // Regardless of whether they have read/write/mod perms. (just not deny)
                $service = UserAccess::SERVICE_HOMEPAGE;
                $denyType = UserAccess::ACCESS_DENY;
                return $this->db->executeQuery("SELECT u.id FROM user u JOIN userAccess ua on u.id = ua.uid WHERE service = $service AND sid = $entityValue AND type != $denyType AND u.deleted_at IS NULL")->fetchAll(PDO::FETCH_COLUMN);
            case Model::ENTITY_TYPE_CLASS:
                // Associated with the class, regardless of role type.
                // For students/teachers you'd need to use a composite segment that intersects the desired roleType.
                $externalIds = [];
                foreach($this->externalClass->getStudents($entityValue) as $student){
                    $externalIds[] = $student['synergy_id'];
                }
                foreach($this->externalClass->getTeacherName($entityValue) as $teacherExternalId){
                    $externalIds[] = $teacherExternalId;
                }
                if(!\count($externalIds)){
                    return [];
                }
                $externalIdString = \implode(',', $externalIds);
                return $this->db->executeQuery("SELECT u.id FROM user u WHERE synergy_id IN ({$externalIdString}) AND u.deleted_at IS NULL")->fetchAll(PDO::FETCH_COLUMN);
            default:
                throw new UnexpectedValueException("Entity type not recognised: {$entityType}");
        }
    }
}
