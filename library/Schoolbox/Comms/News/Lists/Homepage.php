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
     * @param Querystring $params
     * @param User        $user
     * @return array
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \DomainException
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     */
    public function find(Querystring $params, User $user): array {
        $articleIds = $this->newsMapper->findArticleIdsForTopic(
            $this->articleResolver->findArticlesForUser($user),
            Mapper::TOPIC_ALL_ID,
            $params->getLimit(),
            $params->getPageNum(),
            null,
            null,
            $params->getFolderId()
        );

        $articleIds = array_unique($articleIds);

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
}
