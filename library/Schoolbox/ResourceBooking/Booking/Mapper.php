<?php
/**
 * A mapper for bookings.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\ResourceBooking\Booking;
use EOTC\Calendar\Event;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Collection;

class Mapper extends BaseMapper {

    public function mapping() {
        $this->hasTable('resource_booking');
        $this->hasModel('Schoolbox\\ResourceBooking\\Booking\\Model');
        $this->hasMap(
            array(
                'column'     => 'id',
                'property'   => 'id',
                'type'       => self::TYPE_INTEGER,
                'identifier' => true,
            )
        );
        // A single booking may have multiple events (if it's a recurring booking)?
        // It can probably still be related back to a single event though.
        $this->hasMap(
            array(
                'column'   => 'event_id',
                'property' => 'event',
                'type'     => self::TYPE_MODEL,
                'join'     => 'event',
                'join_def' => array(
                    // For now, this is the event mapper.
                    // TODO move the event mapper outside of EOTC
                    'mapper'    => '\\EOTC\\Calendar\\EventMapper',
                    'other_key' => 'id',
                ),
            )
        );
        // It will only have a single resource, though. (There may be related bookings.)
        $this->hasMap(
            array(
                'column'   => 'resource_id',
                'property' => 'resource',
                'type'     => self::TYPE_MODEL,
                'join'     => 'resource',
                'join_def' => array(
                    'mapper'    => '\\Schoolbox\\ResourceBooking\\Item\\Mapper',
                    'other_key' => 'id',
                ),
            )
        );

        // Status and Reviewer
        $this->hasMap(
            array(
                'column'   => 'resource_booking_status_id',
                'property' => 'status',
                'type'     => self::TYPE_MODEL,
                'join'     => 'status',
                'join_def' => array(
                    'mapper'    => '\\Schoolbox\\ResourceBooking\\Booking\\Status\\Mapper',
                    'other_key' => 'id',
                ),
            )
        );
        $this->hasMap(
            array(
                'column'   => 'reviewer_id',
                'property' => 'reviewer',
                'type'     => self::TYPE_MODEL,
                'join'     => 'reviewer',
                'join_def' => array(
                    'mapper'    => '\\Schoolbox\\UserMapper',
                    'other_key' => 'id',
                ),
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

        // Join to related bookings
        $this->hasJoin(
            'related_booking',
            array(
                'mapper'    => '\\Schoolbox\\ResourceBooking\\Booking\\RelatedBooking\\Mapper',
                'this_key'  => 'id',
                'other_key' => 'resource_booking_id',
                'multiple'  => true,
//                // Better M2M joins:
//                'mapper'    => '\\Schoolbox\\ResourceBooking\\Booking\\Mapper',
//                'this_key'  => 'id',
//                'other_key' => 'id',
//                'multiple'  => true,
//                'through'   => array(
//                    'mapper' => '\\Schoolbox\\ResourceBooking\\Booking\\RelatedBooking\\Mapper',
//                    'table'     => 'resource_booking_relation',
//                    'this_key'  => 'resource_booking_id',
//                    'other_key' => 'extra_resource_booking_id',
//                    'two_way'   => true,
//                ),
            )
        );
        $this->registerCallback('postSave', array($this, 'saveRelatedBookings'));
    }

    /**
     * Retrieves all bookings for the given resource.
     *
     * @param int   $resourceId
     * @param int   $fromTimestamp
     * @param int   $toTimestamp
     * @param array $statusIds     Status IDs of events to fetch. By default,
     *                             gets Status\Model::STATUS_PENDING and
     *                             Status\Model::STATUS_APPROVED only.
     *
     * @return Collection
     */
    public function findForResource($resourceId, $fromTimestamp, $toTimestamp, $statusIds = array()) {
        // If using Aura:sql_query
//        $this->queryFactory->newSelect()
//            ->cols($this->getColumnArray('x'))
//            ->from($this->getTable().' AS x')
//            ->join('left', $this->getJoinMapper('event')->getTable().' AS y', $this->getJoinParameters('event', 'x', 'y'))
//        ;
//        $query = $this->createSelectQuery($alias);
//        $this->addJoinToQuery($query, 'event', $alias, 'y');
//        $this->addJoinToQuery($query, 'resource', $alias, '');
//        $query
//            ->where('x.resource_id = :resource_id')
//            ->andWhere('y.startTime <= :fromTimestamp')
//            ->andWhere('y.endTime   >= :toTimestamp')
//        ;

        if (empty($statusIds)) {
            $statusIds = array(Status\Model::STATUS_PENDING, Status\Model::STATUS_APPROVED);
        }

        // Just search on date for now.
        // Events: any event whose start <= endWindow, or end >= startWindow
        $params = array(
            'resource'    => $resourceId,
            'startWindow' => date('Y-m-d', $fromTimestamp),
            'endWindow'   => date('Y-m-d', $toTimestamp),
        );
        $params = array_merge($params, $this->getArrayParameters($statusIds, 'status'));
        $query = "
            SELECT
                {$this->getColumnString('b')},
                {$this->getJoinColumnString('event',        'e')},
                {$this->getJoinColumnString('event.owner',  'eo')},
                {$this->getJoinColumnString('event.author', 'ea')},
                {$this->getJoinColumnString('status',       's')},
                {$this->getJoinColumnString('reviewer',     'r')}
            FROM {$this->getTable()} `b`
            LEFT JOIN {$this->getJoinSql('event',        'b', 'e')}
            LEFT JOIN {$this->getJoinSql('event.owner',  'e', 'eo')}
            LEFT JOIN {$this->getJoinSql('event.author', 'e', 'ea')}
            LEFT JOIN {$this->getJoinSql('status',       'b', 's')}
            LEFT JOIN {$this->getJoinSql('reviewer',     'b', 'r')}
            WHERE b.resource_id = :resource
            AND b.deleted_at IS NULL
            AND NOT (e.endDate < :startWindow OR e.startDate > :endWindow)
            AND s.id IN {$this->getArrayPlaceholders($statusIds, 'status')}
        ";
        $statement = $this->getDatabase()->prepare($query);
        $statement->execute($params);

        // Hydration:
        // progress through each join that was passed.
        return $this->hydrateList(
            $statement, 'b',
            function($mapper, $model, $row) {
                /** @var self  $mapper */
                /** @var Model $model */
                /** @var array $row */
                /** @var Event $event */
                $event = $mapper->hydrateJoin($row, 'event', 'e');
                $event->setOwner($mapper->hydrateJoin($row, 'event.owner', 'eo'));
                $event->setAuthor($mapper->hydrateJoin($row, 'event.owner', 'ea'));
                $model->setEvent($event);
                $model->setStatus($mapper->hydrateJoin($row, 'status', 's'));
                $model->setReviewer($mapper->hydrateJoin($row, 'reviewer', 'r'));
                return $model;
            }
        );
    }

    /**
     * Post-save callback - ensures that related bookings are saved after the
     * item is saved.
     */
    public function saveRelatedBookings(Model $object) {
        // Add save of related items
        $mapper  = $this->getJoinMapper('related_booking');
        $oldList = $mapper->findByColumn('resource_booking_id', $object->getId());
        $newList = new Collection();
        foreach ($object->getRelatedBookingList() as $related) {
            $joinObject = new RelatedBooking\Model();
            $joinObject->setRelatedBooking($related)->setBooking($object);
            $newList->add($joinObject);
        }

        // Save all newList items that aren't in oldList
        // Delete all vice versa
        $diffMethod = function($a, $b) {
            return $a->getRelatedBooking()->getId() == $b->getRelatedBooking()->getId();
        };
        $mapper->save($newList->diff($oldList, $diffMethod));
        $mapper->delete($oldList->diff($newList, $diffMethod));
    }

    /**
     * Is the user a moderator of the item this booking is for?
     *
     * @param  int $bookingId
     * @param  int $userId
     * @return bool
     */
    public function isUserModeratorOfBookingItem($bookingId, $userId) {
        $query = "
            SELECT COUNT(*)
            FROM {$this->getTable()} b
            INNER JOIN {$this->getJoinSql('resource', 'b', 'r')}
            INNER JOIN {$this->getJoinSql('resource.moderator', 'r', 'rm')}
            WHERE b.id=:bookingId AND rm.moderator_id=:userId
        ";
        $statement = $this->getDatabase()->prepare($query);
        $statement->bindValue('bookingId', $bookingId);
        $statement->bindValue('userId',    $userId);
        $statement->execute();
        return $statement->fetchColumn() > 0;
    }

    /**
     * Hydrates this item along with its event and resource.
     *
     * @param  mixed $id
     * @return Model
     */
    public function findById($id) {
        // TODO request joins in find* methods
        $query = "
            SELECT
                {$this->getColumnString('b')},
                {$this->getJoinColumnString('resource', 'r')},
                {$this->getJoinColumnString('event',    'e')}
            FROM {$this->getTable()} b
            INNER JOIN {$this->getJoinSql('resource', 'b', 'r')}
            INNER JOIN {$this->getJoinSql('event',    'b', 'e')}
            WHERE b.id=:bookingId
        ";
        $statement = $this->getDatabase()->prepare($query);
        $statement->bindValue('bookingId', $id);
        $statement->execute();
        $item = $this->hydrate($statement->fetch(\PDO::FETCH_ASSOC), 'b', function($mapper, $model, $row) {
            /** @var self  $mapper */
            /** @var Model $model */
            return $model
                ->setResource($mapper->hydrateJoin($row, 'resource', 'r'))
                ->setEvent($mapper->hydrateJoin($row, 'event', 'e'))
            ;
        });
        $this->throwIfDeleted($item);
        return $item;
    }
}
