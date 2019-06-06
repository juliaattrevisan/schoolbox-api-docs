<?php
/**
 * Index
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Comms\News\Lists;

use Schoolbox\Comms\News\Querystring;
use Schoolbox\News\TopicMapper;
use Schoolbox\User;

class Index extends Base
{
    /**
     * If the user is a superuser or a news moderator
     *
     * Article must be published
     * and article must not be expired
     * and one of the following conditions must be met:
     *    - you're the author
     *    - you approved the article
     *    - you're in the audience
     *
     * Everyone else
     *
     * Article must be published
     * and article must not be expired
     * and one of the following conditions must be met:
     *    - you're the author
     *    - you're in the audience
     *
     * @param Querystring $params
     * @param User        $user
     * @return array
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \DomainException
     * @throws \PDOException
     */
    public function find(Querystring $params, User $user): array  {

        /* You are in the audience of these articles
         * or you accepted/authored them
         */
        $relevantArticles = array_unique(
            array_merge(
                $this->articleResolver->findArticlesForUser($user),
                $this->newsMapper->findAuthoredOrAcceptedArticleIds($user)
            )
        );

        /* If $topicId was provided, restrict data set to articles
         * that were in the specified topic
         */
        if ($params->getSlug()) {
            $topics = TopicMapper::getInstance()->findAll(['order'=>['sequence'=>'ASC']]);

            $topicId = $this->determineSelectedTopicId($topics, $params->getSlug());

            return $this->newsMapper->findArticleIdsForTopic(
                $relevantArticles,
                $topicId,
                $params->getLimit(),
                $params->getPageNum(),
                null,
                null,
                null
            );
        }

        return $relevantArticles;
    }
}
