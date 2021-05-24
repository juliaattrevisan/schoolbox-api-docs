<?php

/**
 * Lists
 *
 * A shortcut to getting certain types of articles via predicate builder etc
 *
 * I can't think of a better name for this right now
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Comms\News\Moderation;

use Schoolbox\Comms\News;
use Schoolbox\GenericCollection;

class Lists
{

    /**
     * Maximum number of Recently Accepted articles to show
     */
    private const NUM_RECENTLY_ACCEPTED = 5;

    /**
     * Maximum number of Recently Rejected articles to show
     */
    private const NUM_RECENTLY_REJECTED = 5;

    /**
     * @var News\Mapper
     */
    private $newsMapper;


    /**
     * Lists constructor.
     * @param News\Mapper $newsMapper
     */
    public function __construct(News\Mapper $newsMapper)
    {
        $this->newsMapper = $newsMapper;
    }

    /**
     * A list of recently-accepted articles
     * Most recently accepted article is listed first
     *
     * @return GenericCollection
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     */
    public function getRecentlyAccepted(): GenericCollection
    {
        return $this->newsMapper->findByPredicates($this->newsMapper->getPredicateApproved(), [
            'limit' => self::NUM_RECENTLY_ACCEPTED,
            'order' => [
                'accepted_at' => 'desc'
            ]
        ]);
    }


    /**
     * A list of rejected/unpublished articles
     * Most recently rejected article is listed first
     *
     * @return GenericCollection
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     */
    public function getRecentlyRejected(): GenericCollection
    {
        return $this->newsMapper->findByPredicates($this->newsMapper->getPredicateRejected(), [
            'limit' => self::NUM_RECENTLY_REJECTED,
            'order' => [
                'rejected_at' => 'desc'
            ]
        ]);
    }
}
