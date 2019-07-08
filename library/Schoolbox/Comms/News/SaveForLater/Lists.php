<?php
/**
 * Lists
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Comms\News\SaveForLater;

use Alaress\Database\Predicate\InvalidColumnException;
use Schoolbox\Collection;
use Schoolbox\Comms\News;

class Lists
{

    /**
     * @var Mapper
     */
    private $saveForLaterMapper;

    /**
     * @var News\Mapper
     */
    private $newsMapper;


    /**
     * Lists constructor.
     * @param Mapper      $saveForLaterMapper
     * @param News\Mapper $newsMapper
     */
    public function __construct(Mapper $saveForLaterMapper, News\Mapper $newsMapper)
    {
        $this->saveForLaterMapper = $saveForLaterMapper;
        $this->newsMapper = $newsMapper;
    }


    /**
     * Find articles that were saved by $userId
     *
     * @param int $userId
     * @return Collection|News\Model[]
     * @throws InvalidColumnException
     */
    public function findForUser(int $userId): Collection
    {
        $ids = $this->findSavedIdsForUser($userId);

        if (\count($ids)) {
            return $this->newsMapper->findByColumn('id', $ids, [
                'order' => [
                    'publish_at' => 'asc'
                ]
            ]);
        }

        return $this->newsMapper->createCollection();
    }


    /**
     * Returns an array of (article_id, 1) tuples for saved articles
     * This allows you to quickly figger out if an article was Saved For Later
     * and assign the state to the Save For Later button accordingly
     *
     * @param int $userId
     * @return array
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     */
    public function findSavedIdsForUser(int $userId): array
    {
        $pb = $this->saveForLaterMapper->getPredicateBuilder();
        $pb->equals('user_id', $userId);

        return $this->saveForLaterMapper->findScalarByPredicates('article_id', $pb);
    }
}
