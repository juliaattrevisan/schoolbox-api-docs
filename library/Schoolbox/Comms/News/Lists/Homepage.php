<?php
/**
 * Homepage
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Comms\News\Lists;

use Schoolbox\Comms\News\Audience;
use Schoolbox\Comms\News\Audience\Segment\Strategy\Folder;
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
        $articleIds = $this->articleResolver->findArticlesForAudience(
            Folder::getEntityType(),
            $params->getFolderId(),
            Audience\Model::RELATIONSHIP_SELF,
            true,
            [ "{$this->newsMapper->getTable()}.publish_at" => 'DESC', "{$this->newsMapper->getTable()}.created_at" => 'DESC'],
            $params->getLimit()
        );

        $idProperty = $this->newsMapper->getIdentifierProperty();

        /** @var GenericCollection|Model[] $articles */
        $articles = $this->loadArticleByIds($articleIds)->indexBy($idProperty);

        foreach ($articles as $article) {
            $article->setAudience(new GenericCollection(Audience\Model::class));
        }

        // Load audiences so module auth rules work in rendered templates
        $rootSegments = $this->audienceMapper->findForArticles($articles->getPropertyValues($idProperty));

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
