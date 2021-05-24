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
 * I foresaw $history as the complete transactional history originally
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
 * (new Transactor($history, $awardMapper))->award($award);
 *
 *
 * If you're assigning to multiple, you could fetch the $history set for all those guys in
 * one collection, then manually filter it out in php town as required, so you can iterate ->award()
 *
 * This will save you on smashing the db for a $history collection for each user; instead, you
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
    private GenericCollection $history;


    /**
     * @var Mapper
     */
    private Mapper $awardMapper;


    /**
     * Transactor constructor.
     *
     * $history is a set of Awards given to a user, but it's calculated outside
     * this class so I can unit test this
     *
     * @param GenericCollection $history
     * @param Mapper            $awardMapper
     */
    public function __construct(GenericCollection $history, Mapper $awardMapper)
    {
        $this->history = $history;
        $this->awardMapper = $awardMapper;
    }


    /**
     * Award a $badge to the person in $history
     *
     * You could perhaps specify $user explicitly here, but you don't need to do that
     * if you rely on $badgeHistory being correctly obtained
     *
     * @param Model $award
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    public function award(Model $award): void
    {

        /* If the history is empty, just add it and leave; but note this allows a
         * race condition in rare circumstances, such as when two requests to $this->award()
         * start with the same empty history, but concurrently insert their own rows
         * There used to be a schema UNIQUE to prevent this, but it's been removed
         */
        if (!$this->history->count()) {
            $this->awardMapper->save($award);
            return;
        }

        /* Otherwise, goals and Achievements have different interpretations of what happened
         * in the history
         */
        if ($award->isGoal()) {
            /* If the $award is a goal, we need to do a deeper inspection as
             * awarding of goals behaves in a special way with respect to
             * a badge's award history for a particular person
             */
            if ($this->history->first()->isGoal()) {
                /* If the most recent awarding of $badge is a goal,
                 * then you cannot award it as a goal again
                 *
                 * You can't have the same goal more than once, concurrently
                 *
                 * Just leave
                 */
                return;
            } elseif ($this->history->first()->isAchievement()) {
                /* If the most recent awarding of $badge is an achievement,
                 * then you can award $badge as a goal
                 *
                 * This allows people to set up goals that people reach, then
                 * set it as a goal again for them to get the achievement again
                 * at a later time
                 */
                $this->awardMapper->save($award);
                return;
            }
        } elseif ($award->isAchievement()) {
            /* If the $award is an achievement, first have a look to see if there
             * is a corresponding goal which we need to touch
             */
            if ($this->history->first()->isGoal()) {
                /* If the most recent awarding of $badge is a goal,
                 * set its award_id field to the current award's id; the goal was reached
                 * since we are giving them the achievement. We need to save that first.
                 */
                $this->awardMapper->save($award);

                /* Giving that record -this- award's id gives us a direct correlation
                 * between a goal and the achievement, so we can conclusively track which
                 * achievement satisfied a goal.
                 */
                $this->history->first()->setAward($award);
                $this->awardMapper->save($this->history->first());
                return;
            } elseif ($this->history->first()->isAchievement()) {
                /* If the most recent awarding of $badge is an achievement,
                 * I guess it can just go in as a new row; this person gets the achievement
                 * multiple times
                 *
                 * Probably check for double-click-submit dupes here, if it happens
                 */
                $this->awardMapper->save($award);
                return;
            }
        }

        /* Unknown badge type; either a dev mistake (no hydration, typo in status),
         * or a new badge type that we haven't seen before
         *
         * Just leave
         */
        return;
    }
}
