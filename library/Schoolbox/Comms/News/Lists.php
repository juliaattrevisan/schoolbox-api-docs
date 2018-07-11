<?php
/**
 * Lists
 *
 * Generates a set of articles for a particular topic
 * Also figures out whether the viewing user has saved any of those articles for later
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Comms\News;

use Schoolbox\User;

class Lists
{

    /**
     * @var Mapper
     */
    private $newsMapper;

    /**
     * @var Policy\Mapper
     */
    private $policyMapper;

    /**
     * @var SaveForLater\Mapper
     */
    private $saveForLaterMapper;


    /**
     * Lists constructor.
     * @param Mapper              $newsMapper
     * @param Policy\Mapper       $policyMapper
     * @param SaveForLater\Mapper $saveForLaterMapper
     */
    public function __construct(Mapper $newsMapper, Policy\Mapper $policyMapper, SaveForLater\Mapper $saveForLaterMapper)
    {
        $this->newsMapper = $newsMapper;
        $this->policyMapper = $policyMapper;
        $this->saveForLaterMapper = $saveForLaterMapper;
    }


    /**
     * @param User $user
     * @param int  $topicId
     * @param int  $limit
     * @return array
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     */
    public function findForTopic(User $user, int $topicId, int $limit): array
    {
        /* Figure out which ones were saved for later
         */
        $policies = $this->policyMapper->findAll()->indexBy('identifier');
        $policy = $policies->getOffset('save_for_later');

        /* Component config can set "All" as its limit
         */
        $articles = $this->newsMapper->findForTopic($topicId, $limit);

        /* Figure out the articles that this user has saved for later
         */
        $savedArticleIds = $this->findSavedArticles($user, $policy);

        /* TODO: Increment Impressions count for everything in $articles
         */

        /* These pieces are needed to represent an article, due to Save For Later
         */
        return [
            'articles' => $articles,
            'readMore' => $policies->getOffset('read_more')->getValue(),
            'saveForLater' => $policy->getValue(),
            'savedArticleIds' => $savedArticleIds,
            'unsaved' => SaveForLater\Mapper::UNSAVED
        ];
    }


    /**
     * Find the article ids that were saved by $user in accordance with policy setting
     *
     * @param User         $user
     * @param Policy\Model $policy
     * @return array
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     */
    private function findSavedArticles(User $user, Policy\Model $policy): array
    {
        if ($policy->getValue()) {
            $savedArticleIds = (new SaveForLater\Lists($this->saveForLaterMapper, $this->newsMapper))->findSavedIdsForUser($user->getId());

            return array_fill_keys($savedArticleIds, SaveForLater\Mapper::SAVED);
        }

        return [];
    }
}
