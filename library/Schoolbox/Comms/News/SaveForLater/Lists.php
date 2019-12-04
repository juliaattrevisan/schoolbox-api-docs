<?php
/**
 * Lists
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
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
        $ids = $this->saveForLaterMapper->findSavedIdsForUser($userId);

        if (\count($ids)) {
            return $this->newsMapper->findByColumnList(['id' => $ids, 'rejected_at' => null], [
                'order' => [
                    'publish_at' => 'asc'
                ]
            ]);
        }

        return $this->newsMapper->createCollection();
    }
}
