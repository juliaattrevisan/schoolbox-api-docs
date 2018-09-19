<?php
/**
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Comms\News\Moderation;

use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Comms\News\Audience\Mapper as AudienceMapper;
use Schoolbox\Comms\News\Audience\Model as Audience;
use Schoolbox\Comms\News\Audience\Segment\Strategy;

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

    /**
     * @param AudienceMapper                $audienceMapper
     * @param UserAccessMapper              $userAccessMapper
     */
    public function __construct(
        AudienceMapper $audienceMapper,
        UserAccessMapper $userAccessMapper
    ) {
        $this->audienceMapper       = $audienceMapper;
        $this->userAccessMapper     = $userAccessMapper;
    }

    /**
     * Returns the ids of users who are capable of moderating this article
     *
     * The business rules, as *intended* to be coded below, are:
     *  - if an article is audienced ONLY to a single homepage, with no modifiers, users with WRITE+ access can moderate
     *  - in all other cases, only superusers and comms officers can moderate (since sub-moderators don't yet exist)
     *
     * @param int $articleId
     * @throws \Schoolbox\Mapper\Exception
     * @return array
     * @todo Add logic to handle the case when an article 1) does not exist, and 2) has 0 audience segments. See SA-6839
     */
    public function findModeratorsForArticle(int $articleId): array
    {
        // start off by finding the audience segments for this article
        $where = $this->audienceMapper->getPredicateBuilder()->equals(AudienceMapper::FIELD_COMMS_NEWS_ARTICLE_ID, $articleId);

        $segments = $this->audienceMapper->findByPredicates($where);

        if ($segments->count() !== 1) {
            // non-trivial case, more than one segment
            return $this->findCommsOfficerIds();
        }

        /** @var Audience $segment */
        $segment = $segments->first();

        if ($segment->getEntityType() !== Strategy\Folder::ENTITY_TYPE || $segment->getRelationship() !== Audience::RELATIONSHIP_SELF) {
            // not a trivial folder segment; bounce this
            return $this->findCommsOfficerIds();
        }

        $folder = \Schoolbox\Resource\Folder\Mapper::getInstance()->createModel($segment->getEntityValue());
        $writePlusIds = $this->userAccessMapper->findUsersInCategory($folder, null, UserAccess::ACCESS_WRITE)->getPropertyValues('id');

        return array_unique(array_merge($writePlusIds, $this->findCommsOfficerIds()));
    }


    /**
     * Returns the ids of users who are comms officer
     *
     * @throws \Schoolbox\Mapper\Exception
     * @return int[]
     */
    private function findCommsOfficerIds(): array
    {
        return $this->userAccessMapper->findUsersForService(UserAccess::SERVICE_COMMS_OFFICER)->getPropertyValues('id');
    }

}
