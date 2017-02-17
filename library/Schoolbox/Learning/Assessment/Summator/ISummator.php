<?php
/**
 * ISummation
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
namespace Schoolbox\Learning\Assessment\Summator;

use \Schoolbox\Collection;
use Schoolbox\Learning\Assessment\IAssessable;
use \Schoolbox\User;

interface ISummator
{
    /**
     * Summation of a generic list of values.
     *
     * Assumes that weight of all values is equal.
     *
     * @param mixed $values The list of values to summate. Needs to be iterable.
     * @return float|null
     */
    public function summateList($values);

    /**
     * Execute summation strategy upon a Collection of
     * IAssessable instances
     *
     * @param Collection $allAssessables
     * @param User $user
     * @param bool $publishedOnly
     * @return float|null
     */
    public function summate(Collection $allAssessables, User $user, $publishedOnly);

    /**
     * Alias for summate() - created because summation of all assessables for a
     * single user is no longer the only type of summation.
     *
     * @param Collection $allAssessables
     * @param User       $user
     * @param bool       $publishedOnly
     * @return float|null
     */
    public function summateAllAssessablesForUser(Collection $allAssessables, User $user, $publishedOnly);

    /**
     * Summates a single assessable's results for all of its participants.
     *
     * @param IAssessable $assessable
     * @param bool        $publishedOnly
     * @return float|null
     */
    public function summateSingleAssessable(IAssessable $assessable, $publishedOnly);

    /**
     * Summates all assessable's results for all participants.
     *
     * This summates each assessable in turn for all of its participants using $subSummator,
     * and then summates the the totals itself.
     *
     * @param Collection $allAssessables
     * @param bool       $publishedOnly
     * @param ISummator  $subSummator
     * @return float|null
     */
    public function summateAllAssessables(Collection $allAssessables, $publishedOnly, ISummator $subSummator);

    /**
     * Get the public-facing title for this summator strat
     *
     * @return string
     */
    public static function getTitle();


    /**
     * If when determining the state of a project by its sub-assessments, the sub-states
     * should be combined with UNION or INTERSECTION, ie ANY or ALL.
     *
     * This is derived from whether the final mark is derived from 1 or multiple sub-assessments.
     *
     * @return bool
     */
    public function isAggregator();
}
