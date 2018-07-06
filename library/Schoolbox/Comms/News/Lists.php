<?php
/**
 * Lists
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Comms\News;

use Alaress\Database\Predicate\Builder;
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
     * Lists constructor.
     * @param Mapper        $newsMapper
     * @param Policy\Mapper $policyMapper
     */
    public function __construct(Mapper $newsMapper, Policy\Mapper $policyMapper)
    {
        $this->newsMapper = $newsMapper;
        $this->policyMapper = $policyMapper;
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

        /* Component config can set "All" as its limit
         */
        $options = [];

        if ($limit) {
            $options = [
                'limit' => $limit
            ];
        }

        /* TODO: Find articles that were audienced to me
         */
        $articles = $this->newsMapper->findAll($options);

        /* Figure out which ones were saved for later
         */
        $policies = $this->policyMapper->findAll()->indexBy('identifier');

        /* Figure out the articles that this user has saved for later
         */
        $saveForLater = $policies->getOffset('save_for_later')->getValue();

        $savedArticleIds = [];

        if ($saveForLater) {
            $pb = new Builder($this->newsMapper->getDatabase(), 'comms_news_saveforlater');
            $pb->equals('user_id', $user->getId());

            $ids = SaveForLater\Mapper::getInstance()->findScalarByPredicates('article_id', $pb);

            $savedArticleIds = array_fill_keys($ids, SaveForLater\Mapper::SAVED);
        }


        /* TODO: Increment Impressions count for everything in $articles
         */

        /* Three pieces are needed to represent an article, due to Save For Later
         */
        return [
            'articles' => $articles,
            'savedArticleIds' => $savedArticleIds,
            'unsaved' => SaveForLater\Mapper::UNSAVED
        ];
    }

}