<?php

/**
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Realtime\Message;

use Alaress\Database\Predicate\Builder as PredicateBuilder;
use PDOException;
use Schoolbox\Collection;
use Schoolbox\Mapper;
use Schoolbox\Permission\Access\UserAccess;

/**
 * Class RerouteOverrideMapper
 *
 * @package Schoolbox\Realtime\Message
 * @method RerouteOverride createModel($idValue = null)
 */
class RerouteOverrideMapper extends Mapper
{
    /**
     * @return bool
     * @throws Mapper\DefinitionException
     * @throws \InvalidArgumentException
     */
    protected function mapping()
    {
        $this->hasTable('msg_reroute_override');
        $this->hasModel(RerouteOverride::class);

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

        $this->uses('delivery', self::TYPE_SET);

        $this->hasJoin('user', array(
            'mapper'     => '\\Schoolbox\\UserMapper',
            'this_key'   => 'user_id',
            'other_key'  => 'id'
        ));

        return true;
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
        unset($columnList['msg_route_key']);

        $results = parent::findByColumnList($columnList, $options);

        //fix arrayToStringConvert
        if (is_array($routeKey)) {
            $regex = "`$routeKey[0]`";
        } else {
            $regex = "`$routeKey`";
        }

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
     * @param string $delivery
     * @param int    $userId        If supplied, the user to check.
     * @param bool   $userExclusive If true, only routes that are exclusive to
     *                              $userId are retrieved.
     * @return Collection|RerouteOverride[]
     * @throws \PDOException
     */
    public function getMatchingRoutes($routeKey, $delivery, $userId = null, $userExclusive = false)
    {
        $userIds = [RerouteOverride::USER_ID_ALL];
        if (isset($userId)) {
            if ($userExclusive) {
                $userIds = array();
            }
            $userIds[] = $userId;
        }

        $placeholders = PredicateBuilder::getArrayPlaceholders($userIds, 'user');
        $parameters   = PredicateBuilder::getArrayParameters($userIds, 'user');

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
            AND `user_id` IN $placeholders
            AND `delivery` = :delivery
        ";
        $quotedKey = $this->getDatabase()->getQuoter()->escapeForLike($routeKey);
        $params    = array(
            'delivery'  => $delivery,
            'routeKey'  => $routeKey,
            'startKey'  => $quotedKey . '.%',
            'middleKey' => '%.' . $quotedKey . '.%',
            'endKey'    => '%.' . $quotedKey,
        );
        $params = array_merge($params, $parameters);
        $statement = $this->getDatabase()->prepare($query);
        $statement->execute($params);
        return $this->hydrateList($statement);
    }


    /**
     * Have message for this route key been muted?
     *
     * @param string $routeKey
     * @param int    $userId If supplied, the user to check.
     *
     * @return int
     * @throws \PDOException
     */
    public function isRouteMuted($routeKey, $userId = null)
    {
        return $this->getMatchingRoutes($routeKey, RoutePermission::MUTE, $userId)->count();
    }

    /**
     * Get muted folder ids indexed by userId
     * @param int|null $userId
     * @return array
     */
    public function getMutedFolders(?int $userId = null): array
    {
        $service = UserAccess::SERVICE_HOMEPAGE;

        $routeKeyRegex = "/service\\.$service\\.([0-9]+)/";

        $routes = $this->getMatchingRoutes("service.$service", RoutePermission::MUTE, $userId);
        $result = [];

        // routes contain results for user 0 (muted in group level) and user $userId (if provided)
        foreach ($routes as $route) {
            preg_match($routeKeyRegex, $route->getRouteKey(), $matches);
            $folderId = is_array($matches) ? (int)$matches[1] : null;
            if (!$folderId) {
                continue;
            }
            $result[$route->getUser()->getId()]   ??= [];
            $result[$route->getUser()->getId()][] = $folderId;
        };
        return $result;
    }

    /**
     * Special Egg delete function
     */
    public function delete($object)
    {
        $collection = $this->getModelAsCollection($object);
        if (count($collection) === 0) {
            return false;
        }

        // delete each object(s)
        $this->database->beginTransaction();
        foreach ($collection as $item) {
            // Run query
            try {
                $this->deleteByColumnList(
                    [
                        'user_id'        => $item->getUser()->getId(),
                        'msg_route_key'  => $item->getRouteKey(),
                        'delivery'       => $item->getDelivery()
                    ]
                );
            } catch (PDOException $e) {
                $this->database->rollBack();
                throw $e;
            }
        }
        return $this->database->commit();
    }
}
