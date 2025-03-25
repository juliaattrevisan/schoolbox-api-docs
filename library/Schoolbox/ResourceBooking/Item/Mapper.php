<?php

/**
 * A mapper for bookable resources.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\ResourceBooking\Item;

use PDO;
use PDOStatement;
use Schoolbox\Collection;
use Schoolbox\File\Mimetype;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Resource\Storage;
use Schoolbox\ResourceBooking\Booking;
use Schoolbox\ResourceBooking\Category;
use Schoolbox\Role;
use Schoolbox\User;

/**
 * @method Model findOneByColumn($columnName, $columnValue, $order = [], $options = [])
 */
class Mapper extends BaseMapper
{
    /**
     * Cache for the list of moderators for a resource: stored because it's
     * possible that the list be requested more than once for a single resource.
     *
     * @var array<int, GenericCollection<User>>
     */
    private array $moderatorCache;

    public function mapping()
    {
        $this->hasTable('resource');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->joins('resource_category_id', Category\Mapper::class, 'id', null, null, 'category', 'category');
        $this->joins('image_hash', Storage\Mapper::class, 'hash', null, null, 'image', 'image');
        $this->joins(
            'resource_moderation_type_id',
            ModerationType\Mapper::class,
            'id',
            null,
            null,
            'moderationType',
            'moderation_type'
        );
        $this->uses('name', self::TYPE_STRING);
        $this->uses('room_code', self::TYPE_STRING);
        $this->uses('description', self::TYPE_STRING);
        $this->uses('min_lead_time', self::TYPE_TIMESPAN);
        $this->uses('min_reservation_time', self::TYPE_TIMESPAN);
        $this->uses('max_reservation_time', self::TYPE_TIMESPAN);
        $this->uses('current_term_restriction', self::TYPE_BOOLEAN);
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('modified_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);

        // Unmapped joins
        $this->hasJoin(
            'moderator',
            [
                'mapper'    => Moderator\Mapper::class,
                'this_key'  => 'id',
                'other_key' => 'resource_id',
                'multiple'  => true,
                // Better M2M joins:
            //                'mapper'    => '\\Schoolbox\\User',
            //                'this_key'  => 'id',
            //                'other_key' => 'id',
            //                'multiple'  => true,
            //                'through'   => '\\Schoolbox\\ResourceBooking\\Item\\Moderator\\Mapper',
            ]
        );
        $this->hasJoin(
            'booking',
            [
                'mapper'    => Booking\Mapper::class,
                'this_key'  => 'id',
                'other_key' => 'resource_id',
                'multiple'  => true,
            ]
        );
        $this->hasJoin(
            'related_resource',
            [
                'mapper'    => RelatedResource\Mapper::class,
                'this_key'  => 'id',
                'other_key' => 'resource_id',
                'multiple'  => true,
            ]
        );
        $this->hasJoin(
            'role',
            [
                'mapper'    => RolePermission\Mapper::class,
                'this_key'  => 'id',
                'other_key' => 'resource_id',
                'multiple'  => true,
            ]
        );
        $this->registerCallback(self::CALLBACK_POSTSAVE, [$this, 'postSave']);
        $this->registerCallback(self::CALLBACK_POSTDELETE, [$this, 'postDelete']);
        $this->registerCallback(self::CALLBACK_POSTSOFTDELETE, [$this, 'postDelete']);
    }

    /**
     * Ensures that moderators and related items are saved after the item is
     * loaded.
     *
     * @param Model $object
     */
    public function postSave(Model $object)
    {
        // Save moderators
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
        $diffMethod = static function (Moderator\Model $a, Moderator\Model $b) {
            return $a->getModerator()->getId() == $b->getModerator()->getId();
        };
        $mapper->save($newList->diff($oldList, $diffMethod));
        $mapper->delete($oldList->diff($newList, $diffMethod));


        // Save related items
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
        $diffMethod = static function (RelatedResource\Model $a, RelatedResource\Model $b) {
            return $a->getRelatedResource()->getId() == $b->getRelatedResource()->getId();
        };
        $mapper->save($newList->diff($oldList, $diffMethod));
        $mapper->delete($oldList->diff($newList, $diffMethod));


        // Save roles
        // This is different to the two methods above, because resource-role
        // isn't a 1-M join: there is an intermediate which stores if the role
        // is enabled.
        $mapper  = $this->getJoinMapper('role');
        $oldList = $mapper
            ->findByColumn('resource_id', $object->getId())
            ->indexByCallback(static function (RolePermission\Model $m) {
                return $m->getRole()->getId();
            });
        $newList = $mapper->createCollection();
        foreach ($object->getRoleList() as $related) {
            $roleId     = $related->getRole()->getId();
            $joinObject = $oldList[$roleId] ?? new RolePermission\Model();
            $joinObject
                ->setRole($related->getRole())
                ->setAllowed($related->getAllowed())
                ->setResource($object);
            $newList->add($joinObject);
        }
        $mapper->save($newList);
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
     * Always join onto category, image and its mime type
     *
     * @return string
     */
    protected function getSelectString()
    {
        return "
            SELECT
                {$this->getColumnString()},
                {$this->getJoinColumnString('category', 'c')},
                {$this->getJoinColumnString('image', 'i')},
                {$this->getJoinColumnString('image.allowedFileExtension', 'im')}
        ";
    }


    /**
     * Always join onto category, image and its mime type
     *
     * @return string
     */
    protected function getFromString()
    {
        $table = $this->database->getQuoter()->quoteIdentifier($this->table);
        return "
            FROM $table
            LEFT JOIN {$this->getJoinSql('category', '', 'c', true)}
            LEFT JOIN {$this->getJoinSql('image', '', 'i', true)}
            LEFT JOIN {$this->getJoinSql('image.allowedFileExtension', 'i', 'im', true)}
        ";
    }


    /**
     * @param PDOStatement $statement
     *
     * @return GenericCollection
     * @throws BaseMapper\Exception
     */
    protected function hydrateSelect(PDOStatement $statement)
    {
        $c = $this->createCollection();

        while ($r = $statement->fetch(PDO::FETCH_ASSOC)) {
            $item = $this->hydrate($r);

            /** @var Category\Model $category */
            $category = $this->hydrateJoin($r, 'category', 'c');
            if ($category) {
                $item->setCategory($category);
            }

            /** @var Storage\Model $image */
            $image = $this->hydrateJoin($r, 'image', 'i');
            if ($image) {
                /** @var Mimetype $mimeType */
                $mimeType = $this->hydrateJoin($r, 'image.allowedFileExtension', 'im');
                if ($mimeType) {
                    $image->setMimetype($mimeType);
                }

                $item->setImage($image);
            }

            $c->add($item);
        }

        return $c;
    }


    /**
     * Overrides hydrate() to populate the list of moderators, and the image.
     *
     * @todo migrate this into hydrateSelect
     *
     * @param array         $row
     * @param string        $alias
     * @param callable|null $callback
     *
     * @return object|Model
     */
    public function hydrate(array $row, $alias = '', $callback = null)
    {
        if (isset($callback)) {
            return parent::hydrate($row, $alias, $callback);
        }
        return parent::hydrate(
            $row,
            $alias,
            function ($mapper, $model, $row) {
                /** @var self $mapper */
                /** @var Model $model */

                // populate the moderator list
                $this->loadModeratorsIntoModel($model);

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
                $statement = $mapper->getDatabase()->prepare($query);
                $statement->execute([$model->getId()]);
                $model->setRelatedResourceList(
                    $relatedModelMapper->hydrateList(
                        $statement,
                        'm',
                        function ($mapper, $model) {
                            $this->loadModeratorsIntoModel($model);
                            return $model;
                        }
                    )
                );

                // populate role permissions
                // Role names are needed for display as well
                $rolePermissionMapper = $mapper->getJoinMapper('role');
                $roleList             = [];
                $rolePermissionList   = $rolePermissionMapper->findByColumn('resource_id', $model->getId());
                foreach ($mapper->getJoinMapper('role.role')->findAll() as $role) {
                    /** @var Role $role */
                    $roleList[$role->getId()] = $role;
                }
                $model->setRoleList($rolePermissionMapper->createCollection());
                foreach ($rolePermissionList as $rolePermission) {
                    /** @var RolePermission\Model $rolePermission */
                    if (array_key_exists($rolePermission->getRole()->getId(), $roleList)) {
                        $rolePermission->setRole($roleList[$rolePermission->getRole()->getId()]);
                        $model->getRoleList()->add($rolePermission);
                    }
                }

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

        $query     = "
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
        $statement->bindValue('searchLike', '%' . $this->getDatabase()->getQuoter()->escapeForLike($keyword) . '%');
        $statement->bindValue('limit', intval($limit), \PDO::PARAM_INT);
        $statement->bindValue('offset', intval($offset), \PDO::PARAM_INT);
        $statement->execute();
        return $this->hydrateList($statement, 're');
    }


    /**
     * Gets count for a resource that matches a search term.
     *
     * @param string $keyword
     * @return int
     * @throws \PDOException
     */
    public function searchCount($keyword)
    {
        $query     = "
            SELECT COUNT(*)
            FROM {$this->getTable()} `re`
            WHERE
              (`re`.`name` LIKE :searchLike OR `re`.`description` LIKE :searchLike OR `re`.`room_code` = :search)
              AND {$this->getSoftDeleteFilterSql('re')}
        ";
        $statement = $this->getDatabase()->prepare($query);
        $statement->bindValue('search', $keyword);
        $statement->bindValue('searchLike', '%' . $this->getDatabase()->getQuoter()->escapeForLike($keyword) . '%');
        $statement->execute();
        return $statement->fetchColumn();
    }


    /**
     * Is the user a moderator of this item?
     *
     * @param int $itemId
     * @param int $userId
     * @return bool
     * @throws BaseMapper\DefinitionException
     */
    public function isUserModerator($itemId, $userId)
    {
        $list = $this->getJoinMapper('moderator')->findByColumnList(
            ['resource_id' => $itemId, 'moderator_id' => $userId],
            ['limit' => 1]
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
     * @param int $moderatorId
     * @return Collection|Model[]
     * @throws BaseMapper\DefinitionException
     * @throws \PDOException
     */
    public function findForModerator($moderatorId)
    {

        $query     = "
            SELECT {$this->getColumnString('r')}
            FROM {$this->getTable()} `r`
            INNER JOIN {$this->getJoinSql('moderator', 'r', 'm')}
            WHERE m.moderator_id = ?
            AND r.deleted_at IS NULL
        ";
        $statement = $this->getDatabase()->prepare($query);
        $statement->execute([$moderatorId]);

        // Hydration:
        // progress through each join that was passed.
        return $this->hydrateList($statement, 'r');
    }


    /**
     * Loads this resource's moderators into its moderator list.
     *
     * Requires a query to fetch all this resource's moderators.
     *
     * @param Model $model
     */
    private function loadModeratorsIntoModel(Model $model): void
    {
        if (!isset($this->moderatorCache[$model->getId()])) {
            $modMapper  = $this->getJoinMapper('moderator');
            $userMapper = $modMapper->getJoinMapper('moderator');
            $query      = "
                SELECT
                    {$modMapper->getColumnString('m')},
                    {$userMapper->getColumnString('u')}
                FROM {$modMapper->getTable()} `m`
                INNER JOIN {$modMapper->getJoinSql('moderator', 'm', 'u')}
                WHERE m.resource_id=?
            ";
            $statement  = $userMapper->getDatabase()->prepare($query);
            $statement->execute([$model->getId()]);
            $this->moderatorCache[$model->getId()] = $userMapper->hydrateList($statement, 'u');
        }
        $model->setModeratorList($this->moderatorCache[$model->getId()]);
    }
}
