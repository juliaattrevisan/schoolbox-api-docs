<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);


namespace Schoolbox\Learning\Moment\Feed;


use Alaress\HTMLPurifier\Adapter\ScrubAdapter;
use Alaress\Web\Controller\Base;
use Schoolbox\Discussion\Comment\Mapper as CommentMapper;
use Schoolbox\Learning\Badge\Award\Mapper as BadgeAwardMapper;
use Schoolbox\Learning\Moment\Evidence\Mapper as EvidenceMapper;
use Schoolbox\Learning\Moment\Evidence\Translator\ModelToArray\Factory;
use Schoolbox\Learning\Moment\Feed\Translator\ModelToArray;
use Schoolbox\User;


class Controller extends Base
{
    private EvidenceMapper $evidenceMapper;

    private BadgeAwardMapper $badgeAwardMapper;


    private CommentMapper $commentMapper;

    public function __construct(
        EvidenceMapper $evidenceMapper,
         BadgeAwardMapper $badgeAwardMapper,
         CommentMapper $commentMapper
         )
    {
        $this->evidenceMapper = $evidenceMapper;
        $this->badgeAwardMapper = $badgeAwardMapper;
        $this->commentMapper = $commentMapper;
    }


    /**
     * Retrieve a user's learning moments and badges, in one API endpoint.
     *
     * @param User $user
     *@since 20.2
     *
     * @api   GET /learning/evidence/user/{userId}/feed
     */
    public function getFeedForUser(User $user): void
    {
        $encodedCursor = $this->request->query->get('cursor');
        $cursor        = new Cursor($this->getDi()->get('databaseInternal'), $user, 10);
        $hydrator      = new Hydrator($this->evidenceMapper, $this->badgeAwardMapper);
        $translator    = new ModelToArray(
            (new Factory($this->router, $this->authorizer, $this->getUser(), $this->commentMapper))->buildWithComments(2),
            // TODO put this in a factory too
            new \Schoolbox\Learning\Badge\Award\Translator\ModelToArray(
                new User\Translator\ModelToArray(),
                new \Schoolbox\Learning\Badge\Translator\ModelToArray(
                    new User\Translator\ModelToArray(),
                    new \Schoolbox\Resource\Storage\Translator\ModelToArray(),
                    new ScrubAdapter(),
                    new \Schoolbox\Learning\Badge\Translator\Links($this->getRouter(), $this->getAuthorizer(), $this->getUser())
                ),
                new \Schoolbox\Learning\Badge\Award\Translator\Links($this->getRouter(), $this->getAuthorizer(), $this->getUser())
            )
        );

        $data          = $cursor->retrieveResults($encodedCursor);
        $mappedData    = $hydrator->hydrateResults($data)
            ->map($translator)
            ->reindex();

        $this->response->content->set(
            $this->buildJsonOutput([
                'data'     => $mappedData,
                'metadata' => [
                    'count'  => $cursor->retrieveResultCount(),
                    'cursor' => [
                        'current' => $encodedCursor ?? $cursor->current($data),
                        'next'    => $cursor->next($data),
                    ],
                ],
            ])
        );
    }
}
