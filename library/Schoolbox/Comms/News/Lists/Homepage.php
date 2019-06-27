<?php
/**
 * Homepage
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Comms\News\Lists;

use Schoolbox\Comms\News\Audience;
use Schoolbox\Comms\News\Mapper;
use Schoolbox\Comms\News\Model;
use Schoolbox\Comms\News\Querystring;
use Schoolbox\Comms\News\SaveForLater;
use Schoolbox\GenericCollection;
use Schoolbox\User;

class Homepage extends Base
{
    /**
     * For everyone:
     *
     * Article must be published
     * and article must not be expired
     * and article must have been audienced to this folder
     * and $user must have READ access to the folder specified in the article's audience
     *
     * @param Querystring     $params
     * @param User            $user
     * @return array
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \DomainException
     * @throws \InvalidArgumentException
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnderflowException
     * @throws \UnexpectedValueException
     */
    public function find(Querystring $params, User $user): array
    {

        /* Article ids in which $user was in the audience, simply by being associated with $folderId
         * The user needs to appear in the class list for this statement to be true
         */
        $specificallyForUser = $this->articleResolver->findArticlesForUser($user);

        /* These article ids are when you have role-based access to $folder
         * $user won't appear in their audience so we have to find them roundabout-ly
         */
        $withRoleBasedPerms = $this->withReadAccess($params->getFolderId());

        /* The uniqued, combined set of unique articles ids including role-based accessibility
         */
        $combined = array_unique(array_merge($specificallyForUser, $withRoleBasedPerms));

        /* Get them all
         */
        $articleIds = $this->newsMapper->findArticleIdsForTopic(
            $combined,
            Mapper::TOPIC_ALL_ID,
            $params->getLimit(),
            $params->getPageNum(),
            null,
            null,
            $params->getFolderId()
        );

        $idProperty = $this->newsMapper->getIdentifierProperty();

        /** @var GenericCollection|Model[] $articles */
        $articles = $this->loadArticleByIds($articleIds, $params, $user)->indexBy($idProperty);

        // Load audiences so module auth rules work in rendered templates
        $rootSegments = Audience\Mapper::getInstance()->findForArticles($articles->getPropertyValues($idProperty));

        foreach ($rootSegments as $rootSegment) {
            $articles[$rootSegment->getCommsNewsArticle()->getId()]->getAudience()[$rootSegment->getId()] = $rootSegment;
        }

        return [
            'articles' => $this->populateMetadata($articles),
            'readMore' => $this->readMoreText,
            'saveForLater' => $this->saveForLaterEnabled,
            'savedArticleIds' => $this->findSavedArticles($user, $this->saveForLaterEnabled),
            'unsaved' => SaveForLater\Mapper::UNSAVED,
        ];
    }


    /**
     * The articles where $folderId was in its audience
     * READ accessors can see all these
     *
     * @param int $folderId
     * @return array
     */
    private function withReadAccess(int $folderId): array
    {
        $articlesWithRoleBasedPermissions = [];

        /* Find a list of folders that are in an article's audience
         * See if there's an intersection; if there is, you can see the article
         */
        $audienceMapper = \Schoolbox\Comms\News\Audience\Mapper::getInstance();

        $result = $audienceMapper->getDatabase()->query("
            SELECT comms_news_article_id, entity_value
            FROM {$audienceMapper->getTable()}
            WHERE entity_type = 'folder'
            AND entity_value = '{$folderId}'
        ");

        foreach ($result as $row) {
            $articlesWithRoleBasedPermissions[] = $row['comms_news_article_id'];
        }

        return array_unique($articlesWithRoleBasedPermissions);
    }
}
