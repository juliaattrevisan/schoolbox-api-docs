<?php

/**
 * Mapper for resource moderation types.
 *
 * This is a lookup table, so it doesn't really need a mapper, but here it is
 * anyway.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\ResourceBooking\Item\RelatedResource;

use Schoolbox\Mapper as BaseMapper;

class Mapper extends BaseMapper
{

    public function mapping()
    {
        $this->hasTable('resource_relation');
        $this->hasModel('Schoolbox\\ResourceBooking\\Item\\RelatedResource\\Model');
        $this->hasMap(
            array(
                'column'     => 'id',
                'property'   => 'id',
                'type'       => self::TYPE_INTEGER,
                'identifier' => true,
            )
        );
        $this->hasMap(
            array(
                'column'   => 'resource_id',
                'property' => 'resource',
                'type'     => self::TYPE_MODEL,
                'join'     => 'resource',
            )
        );
        $this->hasMap(
            array(
                'column'   => 'extra_resource_id',
                'property' => 'relatedResource',
                'type'     => self::TYPE_MODEL,
                'join'     => 'related_resource',
            )
        );
        $this->hasJoin(
            'resource',
            array(
                'mapper'    => '\\Schoolbox\\ResourceBooking\\Item\\Mapper',
                'this_key'  => 'resource_id',
                'other_key' => 'id',
            )
        );
        $this->hasJoin(
            'related_resource',
            array(
                'mapper'    => '\\Schoolbox\\ResourceBooking\\Item\\Mapper',
                'this_key'  => 'extra_resource_id',
                'other_key' => 'id',
            )
        );

        $this->registerCallback('postSave', array($this, 'postSave'));
        $this->registerCallback('postDelete', array($this, 'postDelete'));
    }

    /**
     * Ensure that if resource A is related to resource B, then resource B is
     * related to resource A.
     *
     * @param Model $object
     */
    public function postSave(Model $object)
    {
        // I'm not sure if this covers the case where a relation's resource ID
        // is changed or not.
        // Oh well, links are only ever added or removed, never updated.
        $query = "
            INSERT INTO {$this->getTable()}
            (resource_id, extra_resource_id)
            VALUES (:resourceId, :extraResourceId)
            ON DUPLICATE KEY UPDATE resource_id = :resourceId
        ";
        $statement = $this->getDatabase()->prepare($query);
        $statement->bindValue(
            'resourceId',
            intval($object->getRelatedResource()->getId()),
            \PDO::PARAM_INT
        );
        $statement->bindValue(
            'extraResourceId',
            intval($object->getResource()->getId()),
            \PDO::PARAM_INT
        );
        $statement->execute();
    }

    /**
     * Break the relation between resource B and resource A if the relation
     * between A and B is broken.
     *
     * @param Model $object
     */
    public function postDelete(Model $object)
    {
        $query = "
            DELETE FROM {$this->getTable()}
            WHERE resource_id=:resourceId AND extra_resource_id=:extraResourceId
        ";
        $statement = $this->getDatabase()->prepare($query);
        $statement->bindValue(
            'resourceId',
            intval($object->getRelatedResource()->getId()),
            \PDO::PARAM_INT
        );
        $statement->bindValue(
            'extraResourceId',
            intval($object->getResource()->getId()),
            \PDO::PARAM_INT
        );
        $statement->execute();
    }
}
