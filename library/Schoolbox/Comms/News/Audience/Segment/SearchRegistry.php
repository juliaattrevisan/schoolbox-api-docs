<?php
/**
 * Registry
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Comms\News\Audience\Segment;

use Schoolbox\GenericCollection;
use Schoolbox\User;

class SearchRegistry implements ISearch
{

    /**
     * @var array|ISearch[]
     */
    private $registry;

    /**
     * SearchRegistry constructor.
     *
     * @param User              $user
     * @param GenericCollection|ISearch[] $strategies
     */
    public function __construct(User $user, GenericCollection $strategies)
    {
        foreach($strategies as $strategy){
            if($strategy->canBeTargetedByUser($user)){
                $this->registry[$strategy->getEntityType()] = $strategy;
            }
        }
    }

    /**
     * @inheritdoc
     */
    public function getSearchSql(string $searchPlaceholder = null): string
    {
        $pieces = [];

        /** @var ISearch $strategy */
        foreach ($this->registry as $strategy) {
            $pieces[] = "({$strategy->getSearchSql($searchPlaceholder)})";
        }

        // We can UNION ALL and avoid de-duplication logic as each sub-query has a distinct entity type
        return \implode(' UNION ALL ', $pieces);
    }
}
