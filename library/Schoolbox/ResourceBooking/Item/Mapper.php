<?php

/**
 * A mapper for bookable resources.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\ResourceBooking\Item;

use Alaress\Database\Predicate\Builder;
use PDO;
use PDOStatement;
use Schoolbox\Collection;
use Schoolbox\File\Mimetype;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Permission\Access\UserAccess;
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
        $this->uses('allow_clash_bookings', self::TYPE_BOOLEAN);
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
            SELECT DISTINCT
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
            LEFT JOIN {$this->getJoinSql('role', '', 'role')}
            LEFT JOIN {$this->getJoinSql('moderator', '', 'm')}
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
     * @param string     $keyword
     * @param User       $user
     * @param ?string    $category
     * @param int        $offset
     * @param int        $limit
     * @param string     $sort_by  Sort field, defaults to 'name' but can be 'modified_at'
     * @param string     $sort_dir Sort direction, defaults to 'ASC'
     * @param array<int> $excludeResourceIds - Ids of resources to exclude from search results
     * @return Collection
     */
    public function search(
        string $keyword,
        User $user,
        ?string $category = null,
        int $offset = 0,
        int $limit = 20,
        string $sort_by = 'name',
        string $sort_dir = 'ASC',
        array $excludeResourceIds = [],
    ): Collection {
        return $this->findByPredicates(
            $this->buildSearchPredicate($keyword, $user, $category, $excludeResourceIds),
            [
                'order' => [$sort_by => $sort_dir],
                'limit' => intval($limit),
                'offset' => intval($offset),
            ]
        );
    }

    /**
     * Gets count for a resource that matches a search term.
     *
     * @param string     $keyword
     * @param User       $user
     * @param ?string    $category
     * @param array<int> $excludeResourceIds - Ids of resources to exclude from search results
     * @return int
     * @throws \PDOException
     */
    public function searchCount(
        string $keyword,
        User $user,
        ?string $category = null,
        array $excludeResourceIds = []
    ): int {
        return $this->countByPredicates(
            $this->buildSearchPredicate($keyword, $user, $category, $excludeResourceIds)
        );
    }

    /**
     * Setting up the predicate builder for searching resources
     *
     * @param string      $keyword
     * @param User        $user
     * @param string|null $category
     * @param array<int> $excludeResourceIds - Ids of resources to exclude from search results
     * @return Builder
     */
    private function buildSearchPredicate(
        string $keyword,
        User $user,
        ?string $category = null,
        array $excludeResourceIds = []
    ): Builder {
        $pb = $this->getPredicateBuilder();

        $hasAdminPermission =
            $user->isSuperuser() ||
            $user->hasServicePermission(UserAccess::SERVICE_RESOURCE_BOOKING, UserAccess::ACCESS_FULL);

        if ('' !== $keyword) {
            $likeKeyword = "%" . $this->database->getQuoter()->escapeForLike($keyword) . "%";
            $pb->any(
                $this->getPredicateBuilder()
                     ->like('name', $likeKeyword)
                     ->like('description', $likeKeyword)
                     ->equals('room_code', $keyword)
            );
        }

        if (!$hasAdminPermission) {
            $pb->any(
                $this->getPredicateBuilder()
                     ->all(
                         $this->getPredicateBuilder()
                              ->equals('role.role_id', $user->getRole()->getId())
                              ->equals('role.allowed', 1)
                     )
                     ->equals('m.moderator_id', $user->getId())
            );
        }

        if (null != $category) {
            $pb->equals('c.id', $category);
        }

        if (count($excludeResourceIds) > 0) {
            $pb->notIn('id', $excludeResourceIds);
        }

        return $pb;
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
