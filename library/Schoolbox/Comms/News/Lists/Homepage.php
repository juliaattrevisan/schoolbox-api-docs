<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Comms\News\Lists;

use Schoolbox\Comms\News\Audience;
use Schoolbox\Comms\News\Audience\Segment\Strategy\Folder;
use Schoolbox\Comms\News\Lookup\ReadMore;
use Schoolbox\Comms\News\Model;
use Schoolbox\Comms\News\Querystring;
use Schoolbox\Comms\News\SaveForLater;
use Schoolbox\Comms\News\Statistics\Interactions;
use Schoolbox\Comms\News\Statistics\Mapper;
use Schoolbox\Comms\News\ValidHasReadMore;
use Schoolbox\GenericCollection;
use Schoolbox\User;

/**
 * Class Homepage
 *
 * Get a list of articles for Homepage Components
 *
 * @package Schoolbox\Comms\News\Lists
 */
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
     * @throws \Alaress\Database\Exception
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \DomainException
     * @throws \InvalidArgumentException
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\DefinitionException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnderflowException
     * @throws \UnexpectedValueException
     */
    public function find(Querystring $params, User $user): array
    {
        $articleIds = $this->articleResolver->findArticlesForAudience(
            Folder::getEntityType(),
            $params->getFolderId() === null ? null : (string)$params->getFolderId(),
            Audience\Model::RELATIONSHIP_SELF,
            true,
            [
                /* Split sticky news from non-sticky news
                 */
                "{$this->newsMapper->getTable()}.sticky" => 'DESC',
                "{$this->newsMapper->getTable()}.publish_at" => 'DESC',
                "{$this->newsMapper->getTable()}.created_at" => 'DESC'
            ],
            $params->getLimit()
        );

        $idProperty = $this->newsMapper->getIdentifierProperty();

        /** @var GenericCollection|Model[] $articles */
        $articles = $this->loadArticleByIds($articleIds)->indexBy($idProperty);

        foreach ($articles as $article) {
            $article->setAudience(new GenericCollection(Audience\Model::class));
        }

        /* Attachment counts for each article in this set
         */
        $attachmentCounts = $this->fileMapper->findCounts($articleIds);

        /* Also happen to augment each article with its files when you're using the normal viewing mode
         */
        if (!(bool)$params->getCompactMode()) {
            foreach ($articles as $article) {
                /* Attach any associated files
                 */
                $article->setAttachedFiles($this->newsMapper->getJoinMapper('files')->findByColumn('article_id', $article->getId()));
            }
        }

        /* Tremendous Assumption
         *
         * If Compact Mode is off, then someone read all these articles currently displaying
         * Record an Open for each of them
         */
        if (!(bool)$params->getCompactMode()) {
            $interactions = new Interactions($this->databaseInternal, $this->statisticsMapper, $this->statisticsCumulativeMapper);

            foreach ($articles as $article) {
                $interactions->saveOpen(
                    $article,
                    \Alaress_Date_Format::now(),
                    $user->getId(),
                    $user->getRole()->getType(),
                    $user->getRole()->getId(),
                    Mapper::REFERRER_HOMEPAGE
                );
            }
        }

        // Load audiences so module auth rules work in rendered templates
        $rootSegments = $this->audienceMapper->findForArticles($articles->getPropertyValues($idProperty));

        foreach ($rootSegments as $rootSegment) {
            $articles[$rootSegment->getCommsNewsArticle()->getId()]->getAudience()[$rootSegment->getId()] = $rootSegment;
        }

        /* Build a lookup of Read More links for each article id, which aren't json but
         * Model instances that don't comprehend the Read More status like this
         */
        $readMores = (new ReadMore($this->readMoreText, $this->commentsIsEnabled))->generate($articles);

        return [
            'articles' => $this->populateMetadata($articles)->reindex(),
            'attachmentCounts' => $attachmentCounts,
            'readMores' => $readMores,
            'saveForLater' => $this->saveForLaterEnabled,
            'savedArticleIds' => $this->findSavedArticles($user, $this->saveForLaterEnabled),
            'unsaved' => SaveForLater\Mapper::UNSAVED,
        ];
    }
}
