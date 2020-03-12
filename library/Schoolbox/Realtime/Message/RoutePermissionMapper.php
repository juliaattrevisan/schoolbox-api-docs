<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Realtime\Message;


use Monolog\Logger;
use OutOfBoundsException;
use PDO;
use PDOStatement;
use Schoolbox\Mapper;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Folder;
use Schoolbox\Resource\Folder\NNIAN;
use Schoolbox\RoleMapper;
use Schoolbox\User;

class RoutePermissionMapper extends Mapper
{

    /** @var PDOStatement[] */
    protected $prepared = [];

    /** @var array */
    protected $severityMap = [
        RoutePermission::DIGEST  => Logger::INFO,
        RoutePermission::INSTANT => Logger::NOTICE,
        RoutePermission::PUSH    => Logger::WARNING,
        RoutePermission::EMAIL   => Logger::ERROR,
    ];


    protected function mapping()
    {
        $this->hasTable('msg_route_perm');
        $this->hasModel(RoutePermission::class);

        $this->hasMap([
            'column'     => 'msg_source_id',
            'property'   => 'msgSource',
            'type'       => self::TYPE_MODEL,
            'join'       => 'msg_source',
            'identifier' => true,
        ]);
        $this->hasMap([
            'column'     => 'role_id',
            'property'   => 'role',
            'type'       => self::TYPE_MODEL,
            'join'       => 'role',
            'identifier' => true,
        ]);

        $this->uses('mask', self::TYPE_SET);
        $this->uses('role_default', self::TYPE_SET, null, 'default');

        $this->hasJoin('msg_source', [
            'mapper'    => SourceMapper::class,
            'this_key'  => 'msg_source_id',
            'other_key' => 'id',
        ]);
        $this->hasJoin('role', [
            'mapper'    => RoleMapper::class,
            'this_key'  => 'role_id',
            'other_key' => 'id',
        ]);
    }


    /**
     * @param RoutePermission $object
     * @return array
     */
    public function save($object)
    {
        // if this perm is silent, don't save it; delete it!
        if ($object->getMask() === ['MU'] && $object->getDefault() === ['MU']) {

            // permissions are a composite-key object, so delete() doesn't work
            $cols = [
                'msg_source_id' => $object->getMsgSource()->getId(),
                'role_id'       => $object->getRole()->getId(),
            ];

            $this->deleteByColumnList($cols);

            return [$cols];
        }

        return parent::save($object);
    }


    /**
     * @param int $msgSourceId
     * @param int $roleId is optional to keep method compatible with parent class
     * @return RoutePermission
     * @throws Mapper\Exception
     */
    public function findById($msgSourceId, $roleId = 0): RoutePermission
    {
        return parent::findOneByColumnList([
            'msg_source_id' => $msgSourceId,
            'role_id'       => $roleId,
        ]);
    }


    /**
     * @param User $user
     * @return array
     */
    public function findAllRoutePermissionsForUser(User $user): array
    {
        $stmt = $this->database->query("
            SELECT  ns.id ns_id, ns.slug, ns.label, src.id src_id, src.key, src.description, perm.mask, perm.role_default, reroute.delivery
            FROM msg_source src
            LEFT JOIN groups g ON g.`group_type` = src.`role_type`
            LEFT JOIN msg_route_perm perm ON src.id = perm.msg_source_id AND g.`id` = perm.`role_id`
            LEFT JOIN msg_namespace ns ON ns.id = src.namespace
            LEFT JOIN msg_reroute reroute ON src.id = reroute.msg_source_id AND reroute.user_id = :user_id
            WHERE perm.role_id = :role_id
            ORDER BY `slug` ASC, `key` ASC
        ", [
            'user_id' => $user->getId(),
            'role_id' => $user->getRole()->getId(),
        ]);

        $data = [];

        $schedule = $user->getRole()->getDigestSchedule();

        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {

            if (empty($data[$r['ns_id']])) {
                $data[$r['ns_id']] = [
                    'id'    => $r['ns_id'],
                    'slug'  => $r['slug'],
                    'label' => $r['label'],
                ];
            }

            $mask    = array_fill_keys(explode(',', $r['mask']), true);
            $default = array_fill_keys(explode(',', $r['role_default']), true);

            // if no digest schedule, don't offer it as an option
            if (!$schedule) {
                unset($mask['DI']);
                unset($default['DI']);
            }

            if ($r['delivery']) {
                $delivery = array_fill_keys(explode(',', $r['delivery']), true);

            } else {
                $delivery = $default;
            }

            // apply mask
            $target = array_intersect_key($delivery, $mask);

            // if target falls outside mask, set to default
            if (!count($target)) {
                $target = $default;
            }

            $data[$r['ns_id']]['sources'][$r['src_id']] = [
                'id'          => $r['src_id'],
                'key'         => $r['key'],
                'description' => $r['description'],
                'mask'        => array_keys($mask),
                'default'     => array_keys($target),
            ];
        }

        return $data;
    }


    /**
     * @param string $topic
     * @param int    $roleId
     * @param int    $userId
     * @param int    $folderId
     * @throws Mapper\Exception
     * @return array
     */
    public function findRouteLevels(string $topic, int $roleId, int $userId = 0, int $folderId = 0): array
    {
        // find the routing levels that match this role, user and topic regex
        $stmt = $this->prepare("
            SELECT
                perm.mask, perm.role_default, reroute.delivery
            FROM msg_source src
            LEFT JOIN msg_namespace ns ON src.namespace = ns.id
            LEFT JOIN msg_route_perm perm ON src.id = perm.msg_source_id AND perm.role_id = :role_id
            LEFT JOIN msg_reroute reroute ON src.id = reroute.msg_source_id AND reroute.user_id = :user_id
            LEFT JOIN groups g ON perm.role_id = g.id
            WHERE role_id IS NOT NULL
            AND :topic REGEXP CONCAT(ns.slug, '(\\.[a-z0-9]+)+\\.', src.key, '$')
            AND g.group_type = src.role_type
        ");

        $stmt->execute([
            'role_id' => $roleId,
            'user_id' => $userId,
            'topic'   => $topic,
        ]);

        // we take the first, whether there are multiple or not (keys should ensure there cannot be)
        $r = $stmt->fetch(PDO::FETCH_ASSOC);

        // if no record matches, this message is not appropriate to this user/role, so dismiss
        if (!$r) {
            return [RoutePermission::MUTE];
        }

        $mask     = $r['mask'] ? explode(',', $r['mask']) : [];
        $default  = $r['role_default'] ? explode(',', $r['role_default']) : [];
        $delivery = $r['delivery'] ? explode(',', $r['delivery']) : [];

        // last-chance bounds checking
        $masked = array_intersect($delivery, $mask);

        if (!count($masked)) {
            $masked = array_intersect($default, $mask);
        }

        // find overrides matching both global and user-specific
        $stmt = $this->prepare("
            SELECT user_id, msg_route_key, delivery
            FROM msg_reroute_override override
            WHERE user_id IN (0, :user_id)
        ");

        $stmt->execute([
            'user_id' => $userId,
        ]);

        // No override found. Returning current mask.
        if ($stmt->rowCount() === 0) {
            return $masked;
        }

        // Is this for a homepage? If so, get any direct inheriting descendants.
        // Route overrides inherit from their closest non-inheriting ancestor node.
        if ($folderId === 0 && preg_match('/service\\.' . UserAccess::SERVICE_HOMEPAGE . '\\.(\d+)\\./', $topic, $matches)) {
            $folderId = (int)$matches[1];
        }
        if ($folderId === 0) {
            return $masked;
        }

        $folderMapper = Folder\Mapper::getInstance();
        $folder       = $folderMapper->findCheaplyById($folderId);
        if ($folder->getInherit()) {
            $nearestParent = NNIAN::getInstance($folderMapper)->findNearestNonInheritingAncestralNode($folder);
            if ($nearestParent) {
                $folderId = $nearestParent->getId();
            }
        }

        // topic to search in the overrides should be for non-inheriting ancestor
        $searchTopic = "service." . UserAccess::SERVICE_HOMEPAGE . ".{$folderId}.";

        // if current topic matches route
        $precedence = [];
        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            // no multiword (ie '.#.') support
            $regex = '`' . str_replace('*', '([^\.]+)', $r['msg_route_key']) . '\.`';

            if (preg_match($regex, $searchTopic, $matches, PREG_OFFSET_CAPTURE)) {
                // literal leftness, wildcard rightness, applied level

                $leftness  = $matches[0][1];
                $rightness = isset($matches[1][1]) ? $matches[1][1] : PHP_INT_MAX;

                $precedence[$r['user_id']][$leftness][$rightness] = $r['delivery'];
            }

        }

        // handle overrides
        if (count($precedence)) {
            /*
             * Override precedence is:
             *   1. specific to the user > global
             *   2. how far left the literal first matches
             *   3. how far right the wildcard matches
             */

            // take highest userId
            $a = $precedence[max(array_keys($precedence))];

            // lowest leftness
            $b = $a[min(array_keys($a))];

            // highest rightness
            $c = $b[max(array_keys($b))];

            $masked = explode(',', $c);
        }

        return $masked;
    }

    /**
     * Transparently cache prepared statements rather than creating new ones
     *
     * @param $queryString
     * @return PDOStatement
     */
    public function prepare(string $queryString): PDOStatement
    {
        $hash = sha1($queryString);
        if (empty($this->prepared[$hash])) {
            $this->prepared[$hash] = $this->database->prepare($queryString);
        }
        return $this->prepared[$hash];
    }


    /**
     * Casts a route level (MUTE, DIGEST, INSTANT, PUSH, EMAIL) to a Logger severity
     *
     * @param string $level
     * @throws OutOfBoundsException if the given level does not exist
     * @return int
     */
    public function getSeverity(string $level): int
    {
        if (isset($this->severityMap[$level])) {
            return $this->severityMap[$level];
        }
        throw new OutOfBoundsException("Cannot cast permission '$level' to severity level");
    }

    /**
     * Does `$mask` contain a mute route permission?
     *
     * If so, then the message should be muted.
     *
     * @param array $mask
     * @return bool
     */
    public function isMuted(array $mask): bool
    {
        return in_array(RoutePermission::MUTE, $mask, false);
    }


    /**
     * Does `$mask` contain a digest route permission?
     *
     * If so, then the message should be sent via the message digest.
     *
     * @param array $mask
     * @return bool
     */
    public function isDigest(array $mask): bool
    {
        return in_array(RoutePermission::DIGEST, $mask, false);
    }


    /**
     * Does `$mask` contain an instant route permission?
     *
     * If so, then the message should be sent immediately to Schoolbox's in-site
     * notifications.
     *
     * @param array $mask
     * @return bool
     */
    public function isInstant(array $mask): bool
    {
        return in_array(RoutePermission::INSTANT, $mask, false);
    }


    /**
     * Does `$mask` contain a push route permission?
     *
     * If so, then the message should be sent immediately to the user's
     * Schoolbox mobile app instance, if they have one.
     *
     * @param array $mask
     * @return bool
     */
    public function isPush(array $mask): bool
    {
        return in_array(RoutePermission::PUSH, $mask, false);
    }


    /**
     * Does `$mask` contain an email route permission?
     *
     * If so, then the message should be sent immediately to the user's
     * registered email address, if they have one.
     *
     * @param array $mask
     * @return bool
     */
    public function isEmail(array $mask): bool
    {
        return in_array(RoutePermission::EMAIL, $mask, false);
    }
}
