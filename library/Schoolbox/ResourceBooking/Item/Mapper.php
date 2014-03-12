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
use Schoolbox\Collection;

class Mapper extends BaseMapper {

    public function mapping() {
        $this->hasTable('resource');
        $this->hasModel('Schoolbox\\ResourceBooking\\Item\\Model');
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
            )
        );
        $this->hasMap(
            array(
                'column'   => 'storage_image_id',
                'property' => 'image',
                'type'     => self::TYPE_MODEL,
                'join'     => 'image',
            )
        );
        $this->hasMap(
            array(
                'column'   => 'resource_moderation_type_id',
                'property' => 'moderationType',
                'type'     => self::TYPE_MODEL,
                'join'     => 'moderation_type',
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
            'category',
            array(
                'mapper'    => '\\Schoolbox\\ResourceBooking\\Category\\Mapper',
                'this_key'  => 'resource_category_id',
                'other_key' => 'id',
            )
        );
        $this->hasJoin(
            'image',
            array(
                'mapper'    => '\\Schoolbox\\StorageMapper',
                'this_key'  => 'storage_image_id',
                'other_key' => 'id',
            )
        );
        $this->hasJoin(
            'moderation_type',
            array(
                'mapper'    => '\\Schoolbox\\ResourceBooking\\Item\\ModerationType\\Mapper',
                'this_key'  => 'resource_moderation_type_id',
                'other_key' => 'id',
            )
        );
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
        $this->registerCallback('postSave', array($this, 'postSave'));
    }

    /**
     * Ensures that moderators are saved after the item is loaded.
     */
    public function postSave(Model $object) {
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
    public function hydrate($row, $alias = '', $callback = null) {
        return parent::hydrate(
            $row,
            $alias,
            function($mapper, $model, $row) {
                /** @var self $mapper */
                /** @var Model $model */
                $modMapper   = $mapper->getJoinMapper('moderator');
                $userMapper  = $modMapper->getJoinMapper('moderator');
                $query     = "
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

                $imageId = $model->getImage()->getId();
                if ($imageId) {
                    $imageMapper = $mapper->getJoinMapper('image');
                    try {
                        $model->setImage($imageMapper->findById($imageId));
                    }
                    catch (BaseMapper\Exception $ex) {}
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
     * @return Collection
     */
    public function search($keyword, $offset = 0, $limit = 20) {
        $query = "
            SELECT {$this->getColumnString('re')}
            FROM {$this->getTable()} `re`
            WHERE
              (`re`.`name` LIKE :search OR `re`.`description` LIKE :search)
              AND {$this->getSoftDeleteFilterSql('re')}
            LIMIT :limit OFFSET :offset
        ";
        $statement = $this->getDatabase()->prepare($query);
        $statement->bindValue('search', '%'.$this->getDatabase()->quoteForLike($keyword).'%');
        $statement->bindValue('limit',  intval($limit),  \PDO::PARAM_INT);
        $statement->bindValue('offset', intval($offset), \PDO::PARAM_INT);
        $statement->execute();
        return $this->hydrateList($statement, 're');
    }

}
