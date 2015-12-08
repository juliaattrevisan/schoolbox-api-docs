<?php
/**
 * A mapper for bookable resources.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
 

namespace Schoolbox\ResourceBooking\Item;

use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Resource\Storage;
use Schoolbox\Collection;

class Mapper extends BaseMapper
{

    public function mapping()
    {
        $this->hasTable('resource');
        $this->hasPrototype(new Model());
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
                'column'   => 'resource_category_id',
                'property' => 'category',
                'type'     => self::TYPE_MODEL,
                'join'     => 'category',
                'join_def' => array(
                    'mapper'    => '\\Schoolbox\\ResourceBooking\\Category\\Mapper',
                    'other_key' => 'id',
                )
            )
        );

        $this->joins('image_hash', Storage\Mapper::class, 'hash', null, null, 'image', 'image');

        $this->hasMap(
            array(
                'column'   => 'resource_moderation_type_id',
                'property' => 'moderationType',
                'type'     => self::TYPE_MODEL,
                'join'     => 'moderation_type',
                'join_def' => array(
                    'mapper'    => '\\Schoolbox\\ResourceBooking\\Item\\ModerationType\\Mapper',
                    'this_key'  => 'resource_moderation_type_id',
                    'other_key' => 'id',
                )
            )
        );
        $this->hasMap(
            array(
                'column'   => 'name',
                'property' => 'name',
                'type'     => self::TYPE_STRING,
            )
        );
        $this->hasMap(
            array(
                'column'   => 'room_code',
                'property' => 'roomCode',
                'type'     => self::TYPE_STRING,
            )
        );
        $this->hasMap(
            array(
                'column'   => 'description',
                'property' => 'description',
                'type'     => self::TYPE_STRING,
            )
        );

        // Minimum lead time, min and max reservation time
        $this->hasMap(
            array(
                'column'   => 'min_lead_time',
                'property' => 'minLeadTime',
                'type'     => self::TYPE_TIMESPAN,
            )
        );
        $this->hasMap(
            array(
                'column'   => 'min_reservation_time',
                'property' => 'minReservationTime',
                'type'     => self::TYPE_TIMESPAN,
            )
        );
        $this->hasMap(
            array(
                'column'   => 'max_reservation_time',
                'property' => 'maxReservationTime',
                'type'     => self::TYPE_TIMESPAN,
            )
        );

        // Created/Modified/Deleted
        $this->hasMap(
            array(
                'column'            => 'created_at',
                'property'          => 'createdAt',
                'type'              => self::TYPE_DATETIME,
                'created_timestamp' => true,
            )
        );
        $this->hasMap(
            array(
                'column'            => 'modified_at',
                'property'          => 'modifiedAt',
                'type'              => self::TYPE_DATETIME,
                'updated_timestamp' => true,
            )
        );
        $this->hasMap(
            array(
                'column'   => 'deleted_at',
                'property' => 'deletedAt',
                'type'     => self::TYPE_DATETIME,
            )
        );
        $this->hasSoftDeleteDateField('deleted_at');

        // Joins
        $this->hasJoin(
            'moderator',
            array(
                'mapper'    => '\\Schoolbox\\ResourceBooking\\Item\\Moderator\\Mapper',
                'this_key'  => 'id',
                'other_key' => 'resource_id',
                'multiple'  => true,
                // Better M2M joins:
            //                'mapper'    => '\\Schoolbox\\User',
            //                'this_key'  => 'id',
            //                'other_key' => 'id',
            //                'multiple'  => true,
            //                'through'   => '\\Schoolbox\\ResourceBooking\\Item\\Moderator\\Mapper',
            )
        );
        $this->hasJoin(
            'booking',
            array(
                'mapper'    => '\\Schoolbox\\ResourceBooking\\Booking\\Mapper',
                'this_key'  => 'id',
                'other_key' => 'resource_id',
                'multiple'  => true,
            )
        );
        $this->hasJoin(
            'related_resource',
            array(
                'mapper'    => '\\Schoolbox\\ResourceBooking\\Item\\RelatedResource\\Mapper',
                'this_key'  => 'id',
                'other_key' => 'resource_id',
                'multiple'  => true,
            )
        );
        $this->hasJoin(
            'role',
            array(
                'mapper'    => '\\Schoolbox\\ResourceBooking\\Item\\RolePermission\\Mapper',
                'this_key'  => 'id',
                'other_key' => 'resource_id',
                'multiple'  => true,
            )
        );
        $this->registerCallback('postSave', array($this, 'postSave'));
        $this->registerCallback(self::CALLBACK_POSTDELETE, array($this, 'postDelete'));
        $this->registerCallback(self::CALLBACK_POSTSOFTDELETE, array($this, 'postDelete'));
    }

    /**
     * Ensures that moderators and related items are saved after the item is
     * loaded.
     *
     * @param Model $object
     */
    public function postSave(Model $object)
    {
        $mapper  = $this->getJoinMapper('moderator');
        $oldList = $mapper->findByColumn('resource_id', $object->getId());
        $newList = new Collection();
        foreach ($object->getModeratorList() as $moderator) {
            $joinObject = new Moderator\Model();
            $joinObject->setModerator($moderator)->setResource($object);
            $newList->add($joinObject);
        }

        // Save all newList items that aren't in oldList
        // Delete all vice versa
        $diffMethod = function($a, $b) {
            return $a->getModerator()->getId() == $b->getModerator()->getId();
        };
        $mapper->save($newList->diff($oldList, $diffMethod));
        $mapper->delete($oldList->diff($newList, $diffMethod));

        // Add save of related items
        // TODO either implement Dr's M2M join or have some better way of defining this
        $mapper  = $this->getJoinMapper('related_resource');
        $oldList = $mapper->findByColumn('resource_id', $object->getId());
        $newList = new Collection();
        foreach ($object->getRelatedResourceList() as $related) {
            $joinObject = new RelatedResource\Model();
            $joinObject->setRelatedResource($related)->setResource($object);
            $newList->add($joinObject);
        }

        // Save all newList items that aren't in oldList
        // Delete all vice versa
        $diffMethod = function($a, $b) {
            return $a->getRelatedResource()->getId() == $b->getRelatedResource()->getId();
        };
        $mapper->save($newList->diff($oldList, $diffMethod));
        $mapper->delete($oldList->diff($newList, $diffMethod));

        // Add save of roles too
        // If saving a new set of roles, this item's ID has to be attached
        $roleList = $object->getRoleList();
        foreach ($roleList as $role) {
            $role->setResource($object);
        }
        $this->getJoinMapper('role')->save($roleList);
    }

    /**
     * On delete of this item, delete all associated bookings
     *
     * @param Model $object
     */
    public function postDelete(Model $object)
    {
        $bookingMapper = $this->getJoinMapper('booking');
        $bookings      = $bookingMapper->findByColumn('resource_id', $object->getId());
        $bookingMapper->delete($bookings);
    }

    /**
     * Gets item with this ID.
     *
     * @param int $id
     *
     * @return Model
     * @throws BaseMapper\Exception         if item with $id wasn't found
     * @throws BaseMapper\Exception\Deleted if item with $id is soft-deleted
     */
    public function findById($id)
    {
        $query = "
            SELECT
                {$this->getColumnString('r')},
                {$this->getJoinColumnString('category', 'c')}
            FROM {$this->getTable()} `r`
            LEFT JOIN {$this->getJoinSql('category', 'r', 'c')}
            WHERE r.id = ?
        ";
        $statement = $this->getDatabase()->prepare($query);
        $statement->execute(array($id));

        // Hydration:
        // progress through each join that was passed.
        /** @var Model $item */
        foreach ($statement as $row) {
            $item = $this->hydrate($row, 'r');
            if ($item->getCategory()->getId()) {
                $item->setCategory($this->hydrateJoin($row, 'category', 'c'));
            }
            $this->throwIfDeleted($item);
            return $item;
        }
        throw new BaseMapper\Exception('Instance of object not found');
    }

    /**
     * Overrides hydrate() to populate the list of moderators, and the image.
     *
     * @todo Replace with a method which doesn't involve a query per resource.
     *
     * @param array  $row
     * @param string $alias
     * @param null   $callback
     *
     * @return object
     */
    public function hydrate(array $row, $alias = '', $callback = null)
    {
        if (isset($callback)) {
            return parent::hydrate($row, $alias, $callback);
        }
        return parent::hydrate(
            $row,
            $alias,
            function($mapper, $model, $row) {
                /** @var self $mapper */
                /** @var Model $model */

                // populate the moderator list
                $modMapper   = $mapper->getJoinMapper('moderator');
                $userMapper  = $modMapper->getJoinMapper('moderator');
                $query       = "
                    SELECT
                      {$modMapper->getColumnString('m')},
                      {$userMapper->getColumnString('u')}
                    FROM {$modMapper->getTable()} `m`
                    INNER JOIN {$modMapper->getJoinSql('moderator', 'm', 'u')}
                    WHERE m.resource_id=?
                ";
                $statement = $userMapper->getDatabase()->prepare($query);
                $statement->execute(array($model->getId()));
                $model->setModeratorList($userMapper->hydrateList($statement, 'u'));

                // populate related resources
                $relationMapper     = $mapper->getJoinMapper('related_resource');
                $relatedModelMapper = $relationMapper->getJoinMapper('related_resource');
                $query              = "
                    SELECT
                      {$relationMapper->getColumnString('r')},
                      {$relatedModelMapper->getColumnString('m')}
                    FROM {$relationMapper->getTable()} `r`
                    INNER JOIN {$relationMapper->getJoinSql('related_resource', 'r', 'm')}
                    WHERE r.resource_id=? AND m.deleted_at IS NULL
                ";
                $statement = $userMapper->getDatabase()->prepare($query);
                $statement->execute(array($model->getId()));
                $model->setRelatedResourceList(
                    $relatedModelMapper->hydrateList(
                        $statement, 'm',
                        function($mapper, $model) {
                            return $model;
                        }
                    )
                );

                // populate role permissions
                $roleMapper = $mapper->getJoinMapper('role');
                $model->setRoleList(
                    $roleMapper->findByColumn('resource_id', $model->getId())
                );

                return $model;
            }
        );
    }

    /**
     * Searches for a resource that matches a search term.
     *
     * @param  string $keyword
     * @param  int    $offset
     * @param  int    $limit
     * @param  string $sort_by  Sort field, defaults to 'name' but can be 'modified_at'
     * @param  string $sort_dir Sort direction, defaults to 'ASC'
     * @return Collection
     */
    public function search($keyword, $offset = 0, $limit = 20, $sort_by = 'name', $sort_dir = 'ASC')
    {
        // Ordering
        switch (strtolower($sort_by)) {
            case 'modified_at':
                $order = '`re`.`modified_at`';
                break;
            case 'name':
            default:
                $order = '`re`.`name`';
                break;
        }
        switch (strtolower($sort_dir)) {
            case 'desc':
                $order .= ' DESC ';
                break;
            case 'asc':
            default:
                $order .= ' ASC ';
                break;
        }

        $query = "
            SELECT {$this->getColumnString('re')}
            FROM {$this->getTable()} `re`
            WHERE
              (`re`.`name` LIKE :searchLike OR `re`.`description` LIKE :searchLike OR `re`.`room_code` = :search)
              AND {$this->getSoftDeleteFilterSql('re')}
            ORDER BY $order
            LIMIT :limit OFFSET :offset
        ";
        $statement = $this->getDatabase()->prepare($query);
        $statement->bindValue('search', $keyword);
        $statement->bindValue('searchLike', '%'.$this->getDatabase()->quoteForLike($keyword).'%');
        $statement->bindValue('limit', intval($limit), \PDO::PARAM_INT);
        $statement->bindValue('offset', intval($offset), \PDO::PARAM_INT);
        $statement->execute();
        return $this->hydrateList($statement, 're');
    }

    /**
     * Gets count for a resource that matches a search term.
     *
     * @param  string $keyword
     * @return int
     */
    public function searchCount($keyword)
    {
        $query = "
            SELECT COUNT(*)
            FROM {$this->getTable()} `re`
            WHERE
              (`re`.`name` LIKE :searchLike OR `re`.`description` LIKE :searchLike OR `re`.`room_code` = :search)
              AND {$this->getSoftDeleteFilterSql('re')}
        ";
        $statement = $this->getDatabase()->prepare($query);
        $statement->bindValue('search', $keyword);
        $statement->bindValue('searchLike', '%'.$this->getDatabase()->quoteForLike($keyword).'%');
        $statement->execute();
        return $statement->fetchColumn();
    }

    /**
     * Is the user a moderator of this item?
     *
     * @param  int $itemId
     * @param  int $userId
     * @return bool
     */
    public function isUserModerator($itemId, $userId)
    {
        $list = $this->getJoinMapper('moderator')->findByColumnList(
            array('resource_id' => $itemId, 'moderator_id' => $userId),
            array('limit' => 1)
        );
        return count($list) >= 1;
    }

    /**
     * Finds items for which $moderatorId is a moderator.
     *
     * This only fetches items that the user is a directly defined moderator -
     * it will not fetch all items if the user has moderate access to the
     * resource booking service.
     *
     * @param  int $moderatorId
     * @return Collection|Model[]
     */
    public function findForModerator($moderatorId)
    {

        $query = "
            SELECT {$this->getColumnString('r')}
            FROM {$this->getTable()} `r`
            INNER JOIN {$this->getJoinSql('moderator', 'r', 'm')}
            WHERE m.moderator_id = ?
            AND r.deleted_at IS NULL
        ";
        $statement = $this->getDatabase()->prepare($query);
        $statement->execute(array($moderatorId));

        // Hydration:
        // progress through each join that was passed.
        /** @var Model $item */
        return $this->hydrateList($statement, 'r');
    }
}
