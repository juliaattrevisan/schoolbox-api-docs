<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Realtime\Message;


use Schoolbox\Collection;
use Schoolbox\Mapper;
use Schoolbox\Mapper\Exception;

class RerouteOverrideMapper extends Mapper {

	/**
	 * @return bool
	 */
	protected function mapping() {
		$this->hasTable('msg_reroute_override');
		$this->hasModel('\\Schoolbox\\Realtime\\Message\\RerouteOverride');

		$this->hasMap(array(
			'column'     => 'user_id',
			'property'   => 'user',
			'type'       => self::TYPE_MODEL,
			'identifier' => true,
			'join'       => 'user'
		));
		$this->hasMap(array(
			'column'     => 'msg_route_key',
			'property'   => 'routeKey',
			'type'       => self::TYPE_STRING,
			'identifier' => true,
		));
		$this->hasMap(array(
			'column'     => 'outgoing_level',
			'property'   => 'outgoingLevel',
			'type'       => self::TYPE_INTEGER,
		));

        $this->uses('delivery', self::TYPE_SET);

		$this->hasJoin('user', array(
			'mapper'     => '\\Schoolbox\\UserMapper',
			'this_key'   => 'user_id',
			'other_key'  => 'id'
		));

		return true;
	}


	/**
	 * Returns a collection of overrides that match the given route and, optionally, user ID.
	 *
	 * @param $route
	 * @param int $userId
	 * @return Collection|RerouteOverride[]
	 */
	public function findWhereMatching($route, $userId = 0) {
		$coll = $this->findByColumn('user_id', array(0, $userId));
		$regex = '`'. str_replace('*','[^\.]', $route) . '`';

		return $coll->filterByCallback(function($override) use ($regex) {
			/** @var RerouteOverride $override */
			return preg_match($regex, $override->getRouteKey());
		});

	}


    /**
     * Override to allow for regex pattern searching on the route key.
     * If no capture groups are specified, the result is identical to if this override did not exist
     * If capture groups are specified, each RerouteOverride::routeKey will be an array of the preg_matches
     *
     * TODO deprecate this unique implementation, as its only use case is now covered by isRouteMuted?
     *
     * @param array $columnList
     * @param array $options
     * @return Collection|RerouteOverride[]
     */
    public function findByColumnList($columnList, $options = array())
    {
        // if not searching on this field, just return the parent result
        if (empty($columnList['msg_route_key'])) {
            return parent::findByColumnList($columnList, $options);
        }

        // don't SQL-filter the route key, we'll do it manually here
        $routeKey = $columnList['msg_route_key'];
        unset ($columnList['msg_route_key']);

        $results = parent::findByColumnList($columnList, $options);

        $regex = "`$routeKey`";

        return $results->map(function ($override) use ($regex) {
            /** @var RerouteOverride $override */

            if (preg_match($regex, $override->getRouteKey(), $matches)) {
                // more than 1 result means return regex
                if (count($matches) > 1) {
                    $override->setRouteKey($matches);
                }

                return $override;
            }

            return null;
        }, false);
    }



    /**
     * @param string $routeKey
     * @param int    $outgoingLevel
     * @param int    $userId        If supplied, the user to check.
     * @param bool   $userExclusive If true, only routes that are exclusive to
     *                              $userId are retrieved.
     * @return Collection|RerouteOverride[]
     */
    public function getMatchingRoutes($routeKey, $outgoingLevel, $userId = null, $userExclusive = false)
    {
        $userIds = array(0);
        if (isset($userId)) {
            if ($userExclusive) {
                $userIds = array();
            }
            $userIds[] = $userId;
        }
        // Check all permutations of route key:
        // exact match
        // starts with
        // in middle of route
        // ends with
        // Keep in mind that . is used as a sub-route separator.
        $query = "
            SELECT {$this->getColumnString()}
            FROM {$this->getTable()}
            WHERE (
                  `msg_route_key` = :routeKey
                  OR
                  `msg_route_key` LIKE :startKey
                  OR
                  `msg_route_key` LIKE :middleKey
                  OR
                  `msg_route_key` LIKE :endKey
            )
            AND `user_id` IN {$this->getArrayPlaceholders($userIds, 'user')}
            AND `outgoing_level` = :level
        ";
        $quotedKey = $this->getDatabase()->quoteForLike($routeKey);
        $params    = array(
            'level'     => $outgoingLevel,
            'routeKey'  => $routeKey,
            'startKey'  => $quotedKey.'.%',
            'middleKey' => '%.'.$quotedKey.'.%',
            'endKey'    => '%.'.$quotedKey,
        );
        $params = array_merge($params, $this->getArrayParameters($userIds, 'user'));
        $statement = $this->getDatabase()->prepare($query);
        $statement->execute($params);
        return $this->hydrateList($statement);
    }

    /**
     * Have message for this route key been muted?
     *
     * @param string $routeKey
     * @param int    $userId   If supplied, the user to check.
     *
     * @return string
     */
    public function isRouteMuted($routeKey, $userId = null)
    {
        return $this->getMatchingRoutes($routeKey, 0, $userId)->count();
    }
}
