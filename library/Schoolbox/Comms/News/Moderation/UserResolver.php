<?php
/**
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Comms\News\Moderation;

use Alaress\Database;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Comms\News\Audience\Mapper as AudienceMapper;
use Schoolbox\Comms\News\Audience\Model as Audience;
use PDO;
use Schoolbox\Comms\News\Audience\Segment\EntityType;

/**
 * Responsible for:
 * * Given an article, finding the set of users who moderate it.
 */
class UserResolver implements IUserResolver
{
    /** @var AudienceMapper */
    private $audienceMapper;

    /** @var UserAccessMapper */
    private $userAccessMapper;

    /** @var Database\Connector\IConnector */
    private $db;

    /**
     * @param AudienceMapper                $audienceMapper
     * @param UserAccessMapper              $userAccessMapper
     */
    public function __construct(
        AudienceMapper $audienceMapper,
        UserAccessMapper $userAccessMapper,
        Database\Connector\IConnector $db
    ) {
        $this->audienceMapper       = $audienceMapper;
        $this->userAccessMapper     = $userAccessMapper;
        $this->db                   = $db;
    }

    /**
     * Returns the ids of users who are capable of moderating this article
     *
     * The business rules, as *intended* to be coded below, are:
     *  - if an article is audienced ONLY to a single homepage, with no modifiers, users with WRITE+ access can moderate
     *  - in all other cases, only superusers and comms officers can moderate (since sub-moderators don't yet exist)
     *
     * @param \Schoolbox\Comms\News\Model $article
     * @throws \Schoolbox\Mapper\Exception
     * @return int[]
     * @todo Add logic to handle the case when an article has 0 audience segments. See SA-6839
     */
    public function findModeratorsForArticle(\Schoolbox\Comms\News\Model $article): array
    {
        // Default moderators (superusers and comms officers)
        $superuserIds = $this->db->executeQuery(
            'SELECT u.id FROM user u JOIN superusers su ON u.id = su.id AND u.deleted_at IS NULL')
            ->fetchAll(PDO::FETCH_COLUMN);
        $moderators = array_unique(array_merge($superuserIds, $this->findCommsOfficerIds()));

        $rootSegments = $this->audienceMapper->findByColumn(AudienceMapper::FIELD_COMMS_NEWS_ARTICLE_ID, $article->getId());

        if ($rootSegments->count() !== 1) {
            // non-trivial case, more than one segment
            return $moderators;
        }

        /** @var Audience $segment */
        $segment = $rootSegments->first();

        if ($segment->getEntityType() !== \Schoolbox\Comms\News\Audience\Segment\Strategy\Folder::getEntityType() || $segment->getRelationship() !== Audience::RELATIONSHIP_SELF) {
            // not a trivial folder segment; bounce this
            return $moderators;
        }

        $folder = \Schoolbox\Resource\Folder\Mapper::getInstance()->createModel($segment->getEntityValue());
        $writePlusIds = $this->userAccessMapper->findUsersInCategory($folder, null, UserAccess::ACCESS_WRITE)->getPropertyValues('id');

        return array_unique(array_merge($writePlusIds, $moderators));
    }


    /**
     * Returns the ids of users who are comms officer
     *
     * @throws \Schoolbox\Mapper\Exception
     * @return int[]
     */
    private function findCommsOfficerIds(): array
    {
        return $this->userAccessMapper->findUsersForService(UserAccess::SERVICE_NEWS)->getPropertyValues('id');
    }

}
