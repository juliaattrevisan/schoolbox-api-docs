<?php
/**
 * Lists
 *
 * A shortcut to getting certain types of articles via predicate builder etc
 *
 * I can't think of a better name for this right now
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Comms\News\Moderation;

use Alaress\Database\Predicate\Builder;
use Schoolbox\Comms\News;
use Schoolbox\GenericCollection;
use Schoolbox\UserMapper;

class Lists
{
    /**
     * @var News\Mapper
     */
    private $newsMapper;

    /**
     * @var UserMapper
     */
    private $userMapper;


    /**
     * Lists constructor.
     * @param News\Mapper $newsMapper
     * @param UserMapper  $userMapper
     */
    public function __construct(News\Mapper $newsMapper, UserMapper $userMapper)
    {
        $this->newsMapper = $newsMapper;
        $this->userMapper = $userMapper;
    }


    /**
     * Submitted articles that require moderation
     * Oldest article is listed first
     *
     * @return GenericCollection
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     */
    public function getNeedModeration(): GenericCollection
    {
        $builder = (new Builder($this->userMapper->getDatabase(), 'comms_news_article'))
            ->notNull('submitted_at')
            ->isNull('moderated_at');

        return $this->newsMapper->findByPredicates($builder, [
            'order' => [
                'submitted_at' => 'asc'
            ]
        ]);
    }


    /**
     * A list of recently-modified articles
     * Most recently article is listed first
     *
     * @return GenericCollection
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     */
    public function getRecentlyModerated(): GenericCollection
    {
        $builder = (new Builder($this->userMapper->getDatabase(), 'comms_news_article'))
            ->notNull('moderated_at');

        return $this->newsMapper->findByPredicates($builder, [
            'order' => [
                'moderated_at' => 'desc'
            ]
        ]);
    }
}
