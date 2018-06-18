<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Comms\News\Audience;

use Alaress\Database;
use External_User;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\UserMapper;
use Schoolbox\Resource\Folder;
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

    /** @var UserMapper */
    private $userMapper;

    /** @var External_User */
    private $externalUser;

    /** @var Folder\Mapper */
    private $folderMapper;

    /** @var \External_Class */
    private $externalClass;


    /**
     * @param Database\Connector\IConnector $db
     * @param UserMapper                    $userMapper
     * @param External_User                 $externalUser
     * @param Folder\Mapper                 $folderMapper
     * @param External_Class                $externalClass
     */
    public function __construct(
        Database\Connector\IConnector $db,
        UserMapper $userMapper,
        External_User $externalUser,
        Folder\Mapper $folderMapper,
        External_Class $externalClass
    ) {
        $this->db            = $db;
        $this->userMapper    = $userMapper;
        $this->externalUser  = $externalUser;
        $this->folderMapper  = $folderMapper;
        $this->externalClass = $externalClass;
    }


    /**
     * Returns the ids of users who are in the article's audience
     *
     * @param int $articleId
     * @return int[]
     * @throws UnexpectedValueException If we attempt to build a query with an unrecognised entity type
     * @todo consider requiring a hydrated article, with all audience segments hydrated
     */
    public function findUsersForArticle(int $articleId): array
    {
        $userIds = [];

        $table        = Mapper::TABLE_NAME;
        $articleField = Mapper::FIELD_COMMS_NEWS_ARTICLE_ID;

        /** @var mixed[][] $rootAudienceSegments */
        $rootAudienceSegments = $this->db->executeQuery("SELECT * FROM {$table} WHERE {$articleField} = {$articleId}")->fetchAll();

        foreach ($rootAudienceSegments as $rootAudienceSegment) {
            // Root level segments are always a UNION
            /** @noinspection SlowArrayOperationsInLoopInspection */
            $userIds = \array_merge($userIds, $this->findUsersForAudienceSegment($rootAudienceSegment));
        }
        return \array_unique($userIds);
    }


    /**
     * Returns the ids of users in an audience segment
     *
     * @param array $audienceSegment
     * @return int[]
     * @throws UnexpectedValueException If we attempt to build a query with an unrecognised entity type
     */
    private function findUsersForAudienceSegment(array $audienceSegment): array
    {
        $entityType = $audienceSegment[Mapper::FIELD_ENTITY_TYPE];

        // Simple base case:
        if (Model::ENTITY_TYPE_COMPOSITE !== $entityType) {
            $userIds = $this->getUserIds($entityType, $audienceSegment[Mapper::FIELD_ENTITY_TYPE]);
            return $this->applyRelationshipFilter($userIds, $audienceSegment[Mapper::FIELD_RELATIONSHIP]);
        }

        // Recursive composite case:
        $table         = Mapper::TABLE_NAME;
        $fieldParentId = Mapper::FIELD_PARENT_ID;
        $stmt          = $this->db->executeQuery("SELECT * FROM $table WHERE $fieldParentId = {$audienceSegment[Mapper::FIELD_ID]}");

        $aggregateUsers = [];
        $firstRow       = true;
        /** @noinspection PhpAssignmentInConditionInspection */
        while($row = $stmt->fetch()){
            if($firstRow){
                $aggregateUsers = $this->findUsersForAudienceSegment($row);
                $firstRow = false;
            } else {
                $aggregateUsers = array_intersect($aggregateUsers, $this->findUsersForAudienceSegment($row));
            }
        }

        return $this->applyRelationshipFilter($aggregateUsers, $audienceSegment[Mapper::FIELD_RELATIONSHIP]);
    }


    /**
     * Returns the set of users who are related to the specified users by the given relationship filter
     *
     * @param array  $userIds
     * @param string $relationship ['self', 'parents_of', 'teachers_of']
     * @return int[]
     * @throws UnexpectedValueException If an unrecognised relationship modifier is encountered
     */
    private function applyRelationshipFilter(array $userIds, string $relationship): array
    {
        if (Model::RELATIONSHIP_SELF === $relationship) {
            return $userIds;
        }

        switch ($relationship) {
            case Model::RELATIONSHIP_PARENTS_OF:
                /** @var \Schoolbox\User[] $users */
                $users             = $this->userMapper->findByColumn($this->userMapper->getIdentifierColumn(), $userIds);
                $parentExternalIds = [];
                foreach ($users as $user) {
                    $userContacts = $this->externalUser->getStudentContactList($user->getExternalId());
                    foreach ($userContacts as $userContact) {
                        if ($userContact['StudentContactID']) {
                            $parentExternalIds[] = $userContact['StudentContactID'];
                        }
                        if ($userContact['StudentContactSpouseID']) {
                            $parentExternalIds[] = $userContact['StudentContactSpouseID'];
                        }
                    }
                }
                return $this->userMapper->findByColumn('synergy_id', $parentExternalIds)->getPropertyValues($this->userMapper->getIdentifierProperty());
            case Model::RELATIONSHIP_TEACHERS_OF:
                $allFolders = $this->folderMapper->createCollection();
                /** @var \Schoolbox\User[] $users */
                $users              = $this->userMapper->findByColumn($this->userMapper->getIdentifierColumn(), $userIds);
                $teacherExternalIds = [];
                foreach ($users as $user) {
                    // Find all folders where the user is a learner, or a class member
                    $allFolders = $allFolders->mergeUnique($this->folderMapper->findForUser($user, true, UserAccess::ACCESS_READ, true), 'id');
                }
                // Then find the teachers of these classes from synergetic.
                // TODO are group instructors considered teachers here?
                foreach ($allFolders as $folder) {
                    foreach ($folder->getCodes() as $code) {
                        /** @noinspection SlowArrayOperationsInLoopInspection */
                        $teacherExternalIds = \array_merge($teacherExternalIds, $this->externalClass->getTeacherName($code->getCode()));
                    }
                }
                return $this->userMapper->findByColumn('synergy_id', $teacherExternalIds)->getPropertyValues('id');
            default:
                throw new UnexpectedValueException("Unrecognised relationship modifier: {$relationship}");
        }
    }


    /**
     * Returns a set of userIds that match a given entity type and identifier
     *
     * @param string $entityType
     * @param string $entityValue
     * @return int[]
     * @throws UnexpectedValueException If an unrecognised entity type is passed
     */
    private function getUserIds(string $entityType, string $entityValue): array
    {
        if (null === $entityValue) {
            $predicate = 'IS NULL';
        } else {
            $predicate = "= {$entityValue}";
        }

        switch ($entityType) {
            case Model::ENTITY_TYPE_USER:
                return [$entityValue];
            case Model::ENTITY_TYPE_SUPER:
                return $this->db->executeQuery('SELECT u.id FROM user u JOIN superusers su ON u.id = su.id')->fetchAll(PDO::FETCH_COLUMN);
            case Model::ENTITY_TYPE_CAMPUS:
                return $this->db->executeQuery("SELECT u.id FROM user u JOIN userCampus uc ON u.id = uc.uid WHERE uc.campus {$predicate}")->fetchAll(PDO::FETCH_COLUMN);
            case Model::ENTITY_TYPE_ROLE:
                return $this->db->executeQuery("SELECT u.id FROM user u WHERE u.group_id {$predicate}")->fetchAll(PDO::FETCH_COLUMN);
            case Model::ENTITY_TYPE_ROLE_TYPE:
                return $this->db->executeQuery("SELECT u.id FROM user u JOIN groups g ON u.group_id = g.id WHERE g.group_type {$predicate}")->fetchAll(PDO::FETCH_COLUMN);
            case Model::ENTITY_TYPE_SCHOOL_HOUSE:
                return $this->db->executeQuery("SELECT u.id FROM user u WHERE u.house {$predicate}")->fetchAll(PDO::FETCH_COLUMN);
            case Model::ENTITY_TYPE_RES_HOUSE:
                return $this->db->executeQuery("SELECT u.id FROM user u WHERE u.residential_house_id {$predicate}")->fetchAll(PDO::FETCH_COLUMN);
            case Model::ENTITY_TYPE_YEAR:
                return $this->db->executeQuery("SELECT u.id FROM user u WHERE u.year {$predicate}")->fetchAll(PDO::FETCH_COLUMN);
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
                return $this->db->executeQuery("SELECT u.id FROM user u JOIN userAccess ua ON u.id = ua.uid WHERE {$whereClause}")->fetchAll(PDO::FETCH_COLUMN);
            case Model::ENTITY_TYPE_PASTORAL_GROUP:
                return $this->db->executeQuery("SELECT u.id FROM user u JOIN pastoral_group_member pgm ON pgm.user_id = u.id WHERE pgm.group_id {$predicate}")->fetchAll(PDO::FETCH_COLUMN);
            case Model::ENTITY_TYPE_GROUP:
                // Regardless of whether they have read/write/mod perms. (just not deny)
                $service = UserAccess::SERVICE_HOMEPAGE;
                $denyType = UserAccess::ACCESS_DENY;
                return $this->db->executeQuery("SELECT u.id FROM user u JOIN userAccess ua on u.id = ua.uid WHERE service = $service AND sid = $entityValue AND type != $denyType")->fetchAll(PDO::FETCH_COLUMN);
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
                return $this->db->executeQuery("SELECT u.id FROM user u WHERE synergy_id IN ({$externalIdString})")->fetchAll(PDO::FETCH_COLUMN);
            default:
                throw new UnexpectedValueException("Entity type not recognised: {$entityType}");
        }
    }
}
