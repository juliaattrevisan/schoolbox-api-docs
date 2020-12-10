<?php
/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Learning\Badge\Award;

use Schoolbox\GenericCollection;
use Schoolbox\Learning\Badge;


/**
 * Class Transactor
 *
 * Goals need a special treatment based on what was previously awarded
 * I foresaw $badgeHistory as the complete transactional history originally
 * but I think we can get away with $badgeHistory being just the most recently
 * awarded instance of $badge
 *
 * Anyway, usage would be something like this when you're assigning to one particular recipient
 *
 *
 * $awardMapper = Award\Mapper::getInstance();
 *
 * $history = $awardMapper->findByColumnList([
 *      'recipient_id' => $user->getId(),
 *      'badge_id' => $badge->getId()
 * ], ['order' => ['awarded_at' => 'desc']]);
 *
 * (new Transactor($history, $awardMapper))->award($badge);
 *
 *
 * If you're assigning to multiple, you could fetch the $badgeHistory set for all those guys in
 * one collection, then manually filter it out in php town as required, so you can iterate ->award()
 *
 * This will save you on smashing the db for a $badgeHistory collection for each user; instead, you
 * can get everyone's history in one query...but you'll consume more memory
 *
 * @package Schoolbox\Learning\Badge\Award
 */
class Transactor
{

    /**
     * A list of Award instances since we need to inspect
     * the past to decide the future
     *
     * @var GenericCollection
     */
    private GenericCollection $badgeHistory;


    /**
     * @var Mapper
     */
    private Mapper $awardMapper;


    /**
     * Transactor constructor.
     *
     * $badgeHistory is a set of Awards given to a user, but it's calculated outside
     * this class so I can unit test this
     *
     * @param GenericCollection $badgeHistory
     * @param Mapper            $awardMapper
     */
    public function __construct(GenericCollection $badgeHistory, Mapper $awardMapper)
    {
        $this->badgeHistory = $badgeHistory;
        $this->awardMapper = $awardMapper;
    }


    /**
     * Award a $badge to the person in $badgeHistory
     *
     * You could perhaps specify $user explicitly here, but you don't need to do that
     * if you rely on $badgeHistory being correctly obtained
     *
     * @param Model $award
     */
    public function award(Model $award): void
    {

        /* If the $award is a goal, we need to do a deeper inspection as
         * awarding of goals behaves in a special way with respect to
         * a badge's award history for a particular person
         */

            /* If the most recent awarding of $badge is a goal,
             * then you cannot award it as a goal again
             *
             * You can't have the same goal more than once, concurrently
             *
             * Just leave
             */

            /* If the most recent awarding of $badge is an achievement,
             * then you can award $badge as a goal
             *
             * This allows people to set up goals that people reach, then
             * set it as a goal again for them to get the achievement again
             * at a later time
             */

        /* If the $award is an achievement, first have a look to see if there
         * is a corresponding goal which we need to touch
         */

            /* If the most recent awarding of $badge is a goal,
             * set its reached_at field to the current datetime; the goal was reached
             * since we are giving them the achievement
             *
             * reached_at Currently Doesn't Exist, we would need to add it
             * It will also enable you to obtain a list of goals that were reached
             * without having to this crazy $badgeHistory inspection
             *
             * This would be better if badge_user had a primary key, but we can
             * probably get by and update it using the instance and a mapper save
             * (we already have the instance so we can take advantage of this)
             */

            /* If the most recent awarding of $badge is an achievement,
             * I guess it can just go in as a new row; this person gets the achievement
             * multiple times
             *
             * Probably check for double-click-submit dupes here, if it happens
             */

        /* Unknown badge type; either a dev mistake (no hydration, typo in status),
         * or a new badge type that we haven't seen before
         *
         * Just leave
         */
    }
}