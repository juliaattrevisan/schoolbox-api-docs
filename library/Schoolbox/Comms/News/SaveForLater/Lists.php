<?php
/**
 * Lists
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Comms\News\SaveForLater;

use Alaress\Database\Predicate\Builder;
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
     * @param int $userId
     * @return Collection
     * @throws InvalidColumnException
     */
    public function findForUser(int $userId): Collection
    {
        $builder = (new Builder($this->saveForLaterMapper->getDatabase(), 'comms_news_saveforlater'))
            ->equals('user_id', $userId);

        $ids = $this->saveForLaterMapper->findScalarByPredicates('article_id', $builder);

        if (\count($ids)) {
            return $this->newsMapper->findByColumn('id', $ids);
        }

        return new Collection();
    }
}