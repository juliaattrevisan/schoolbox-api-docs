<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Comms\News\Moderation;

use Alaress\Database;
use DomainException;
use InvalidArgumentException;
use PDOException;
use Schoolbox\Comms;
use Schoolbox\Comms\News\Audience;
use Schoolbox\Mapper;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Resource\Folder;
use PDO;
use UnderflowException;

use function array_intersect;
use function array_map;
use function array_merge;
use function array_unique;

/**
 * Responsible for:
 * * Given an article, finding the set of users who moderate it.
 */
class UserResolver implements IUserResolver
{
    /** @var Audience\Mapper */
    private $audienceMapper;

    /** @var UserAccessMapper */
    private $userAccessMapper;

    /** @var Database\Connector\IConnector */
    private $db;


    /**
     * @param Audience\Mapper               $audienceMapper
     * @param UserAccessMapper              $userAccessMapper
     * @param Database\Connector\IConnector $db
     */
    public function __construct(
        Audience\Mapper $audienceMapper,
        UserAccessMapper $userAccessMapper,
        Database\Connector\IConnector $db
    ) {
        $this->audienceMapper   = $audienceMapper;
        $this->userAccessMapper = $userAccessMapper;
        $this->db               = $db;
    }


    /** @inheritDoc */
    public function findModeratorsForArticle(Comms\News\Model $article): array
    {
        return array_unique(array_merge(
            $this->getSuperuserIds(),
            $this->getGlobalNewsModerators(),
            $this->getAudienceModerators($article)
        ));
    }


    /**
     * @param Comms\News\Model $article
     * @return array
     * @throws Database\Predicate\InvalidColumnException
     * @throws InvalidArgumentException
     * @throws Mapper\DefinitionException
     * @throws Mapper\Exception
     * @throws Mapper\Exception\Deleted
     * @throws Mapper\Exception\InsufficientHydrationException
     * @throws PDOException
     * @throws UnderflowException
     * @throws DomainException
     */
    public function getAudienceModerators(Comms\News\Model $article): array
    {
        $rootSegments = $this->audienceMapper->findForArticles([$article->getId()]);

        // Special case, no audience
        if (!$rootSegments->count()) {
            return [];
        }

        // To moderate an article due to its audience, users must be able to moderate all root segments
        $audienceModerators = null;
        foreach ($rootSegments as $rootSegment) {
            $segmentModerators = $this->getSegmentModerators($rootSegment);
            if ($audienceModerators === null) {
                $audienceModerators = $segmentModerators;
            } else {
                $audienceModerators = array_intersect($audienceModerators, $segmentModerators);
            }
            if (!count($audienceModerators)) {
                break;
            }
        }

        return $audienceModerators;
    }


    /**
     * Returns the id of users who can moderate the specified audience segment
     *
     * @param Audience\Model $segment
     * @return int[]
     * @throws Database\Predicate\InvalidColumnException
     * @throws InvalidArgumentException
     * @throws PDOException
     * @throws Mapper\DefinitionException
     * @throws Mapper\Exception
     * @throws Mapper\Exception\Deleted
     * @throws UnderflowException
     * @throws Mapper\Exception\InsufficientHydrationException
     */
    private function getSegmentModerators(Audience\Model $segment): array
    {
        if ($segment->isSimple()) {
            return $this->getLeafSegmentModerators($segment);
        }

        // A user can moderator a composite segment if they can moderate any child segment
        $childModerators = [];
        foreach ($segment->getChildren() as $childSegment) {
            $childModerators = array_merge($this->getSegmentModerators($childSegment));
        }
        return $childModerators;
    }


    /**
     * Returns the id of users who can moderate the specified audience leaf segment
     *
     * @param Audience\Model $segment
     * @return int[]
     * @throws Database\Predicate\InvalidColumnException
     * @throws InvalidArgumentException
     * @throws PDOException
     * @throws Mapper\DefinitionException
     * @throws Mapper\Exception
     * @throws Mapper\Exception\Deleted
     * @throws UnderflowException
     */
    private function getLeafSegmentModerators(Audience\Model $segment): array
    {
        if ($segment->getEntityType() !== Audience\Segment\Strategy\Folder::getEntityType()) {
            return [];
        }
        if ($segment->getEntityValue() === null) {
            return [];
        }
        if ($segment->getRelationship() !== Audience\Model::RELATIONSHIP_SELF) {
            return [];
        }

        // Need to hydrate folder codes to ensure we get SIS teachers
        $folder       = Folder\Mapper::getInstance()->findById($segment->getEntityValue());
        $writePlusIds = $this->userAccessMapper->findUsersInCategory($folder, null, UserAccess::ACCESS_WRITE)->getPropertyValues('id');
        return array_unique(array_map('intval', $writePlusIds));
    }


    /** @inheritDoc */
    public function getSuperuserIds(): array
    {
        $superUserIds = $this->db
            ->executeQuery('SELECT u.id FROM user u JOIN superusers su ON u.id = su.id AND u.deleted_at IS NULL')
            ->fetchAll(PDO::FETCH_COLUMN);
        return array_map('intval', $superUserIds);
    }


    /** @inheritDoc */
    public function getGlobalNewsModerators(): array
    {
        $sql          = "SELECT uid FROM userAccess WHERE service = :service AND type >= :type";
        $moderatorIds = $this->db->executeQuery(
            $sql,
            ['service' => UserAccess::SERVICE_NEWS, 'type' => UserAccess::ACCESS_FULL]
        )->fetchAll(PDO::FETCH_COLUMN);
        return array_map('intval', $moderatorIds);
    }
}
