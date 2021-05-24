<?php

/**
 * Sidebar
 *
 * Generates sidebar vars shared between submodules.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Comms\News;

use Schoolbox\User;

class Sidebar
{

    /**
     * @var Mapper
     */
    private $newsMapper;

    /**
     * @var User
     */
    private $user;

    /**
     * @var string|null
     */
    private $page;


    /**
     * Sidebar constructor.
     * @param Mapper      $newsMapper
     * @param User        $user
     * @param string|null $page
     */
    public function __construct(Mapper $newsMapper, User $user, ?string $page)
    {
        $this->newsMapper = $newsMapper;
        $this->user = $user;
        $this->page = $page;
    }


    /**
     * @return array
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     */
    public function generate(): array
    {
        return [
            'page' => $this->page,
            'moderationCount' => $this->newsMapper->countByPredicates($this->newsMapper->getPredicateAwaitingModeration()),
            'authoredCounts' => [
                'Drafted' => [
                    'count'      => $this->newsMapper->countByPredicates(
                        $this->newsMapper
                            ->getPredicateDraft()
                            ->equals('author_id', $this->user->getId())
                    ),
                    'labelClass' => 'unsubmitted',
                ],
                'Awaiting Moderation' => [
                    'count'      => $this->newsMapper->countByPredicates(
                        $this->newsMapper
                            ->getPredicateAwaitingModeration()
                            ->equals('author_id', $this->user->getId())
                    ),
                    'labelClass' => 'submitted',
                ],
                'Rejected' => [
                    'count'      => $this->newsMapper->countByPredicates(
                        $this->newsMapper
                            ->getPredicateRejected()
                            ->equals('author_id', $this->user->getId())
                    ),
                    'labelClass' => 'submitted-late',
                ],
            ]
        ];
    }
}
