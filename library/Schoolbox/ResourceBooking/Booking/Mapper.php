<?php
/**
 * A mapper for bookings.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\ResourceBooking\Booking;

use Schoolbox\Calendar\Event;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Collection;
use Schoolbox\ResourceBooking\Item;

class Mapper extends BaseMapper
{

    public function mapping()
    {
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
                    'mapper'    => '\\Schoolbox\\Calendar\\EventMapper',
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

        // Name (title) and notes (description)
        $this->hasMap(
            array(
                'column' => 'name',
                'property' => 'name',
                'type' => self::TYPE_STRING,
            )
        );
        $this->hasMap(
            array(
                'column'   => 'notes',
                'property' => 'notes',
                'type'     => self::TYPE_STRING,
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
    }

    /**
    * Find a booking matching a partial name
    *
    * @param $column
    * @param $value
    * @return Collection
    */
    public function findColumnLike($column, $value)
    {
        $searchTerm = '%' . $this->getDatabase()->escapeForLike($value) .'%';

        $query = "
			SELECT {$this->getColumnString('b')}
			FROM {$this->table} b
			WHERE b.{$column} LIKE :like
		";

        $statement = $this->getDatabase()->prepare($query);
        $statement->execute(array(
            'like' => $searchTerm
        ));

        $mapper = new \Schoolbox\ResourceBooking\Booking\Mapper();

        $collection = new \Schoolbox\Collection();

        while ($row = $statement->fetch()) {
            $collection->add($mapper->hydrate($row, 'b'));
        }

        return $collection;
    }

    /**
     * Retrieves all bookings between a given timespan.
     *
     * @param int   $fromTimestamp
     * @param int   $toTimestamp
     * @param array $statusIds     Status IDs of events to fetch. By default,
     *                             gets Status\Model::STATUS_PENDING and
     *                             Status\Model::STATUS_APPROVED only.
     * @param array $extra         Optional values to filter by.
     *                             resource_id: filters by given resource IDs
     *                             owner_id:    filters by this event owner
     *                             category_id: filters by a resource booking category
     *
     * @return Collection|Model[]
     */
    public function findForTimespan($fromTimestamp, $toTimestamp, $statusIds = array(), $extra = array())
    {
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
//            ->andWhere('y.startTime <= :fromTimestamp')
//            ->andWhere('y.endTime   >= :toTimestamp')
//        ;
//        if ($resourceId) {
//            $query->andWhere('x.resource_id = :resource_id');
//        }

        if (empty($statusIds)) {
            $statusIds = array(Status\Model::STATUS_PENDING, Status\Model::STATUS_APPROVED);
        }

        // Just search on date for now.
        // Events: any event whose start <= endWindow, or end >= startWindow
        // Timestamp is passed as the start and end of a given day in the
        // client's timezone, so parse the timezone in the current timezone
        // (which is generally the same as the client's)
        $startWindow = new \DateTime();
        $endWindow   = new \DateTime();
        $startWindow->setTimestamp($fromTimestamp);
        $endWindow->setTimestamp($toTimestamp);
        $params = array(
            'startWindow' => $startWindow->format('Y-m-d H:i:s'),
            'endWindow'   => $endWindow->format('Y-m-d H:i:s'),
        );
        if (!empty($extra['resource_id'])) {
            $params = array_merge($params, $this->getArrayParameters($extra['resource_id'], 'resource'));
        }
        if (!empty($extra['category_id'])) {
            $params = array_merge($params, $this->getArrayParameters($extra['category_id'], 'category'));
        }
        if (!empty($extra['owner_id'])) {
            $params = array_merge($params, $this->getArrayParameters($extra['owner_id'], 'owner'));
        }
        $params = array_merge($params, $this->getArrayParameters($statusIds, 'status'));
        $query = "
            SELECT
                {$this->getColumnString('b')},
                {$this->getJoinColumnString('event',             'e')},
                {$this->getJoinColumnString('event.owner',       'eo')},
                {$this->getJoinColumnString('event.author',      'ea')},
                {$this->getJoinColumnString('status',            's')},
                {$this->getJoinColumnString('reviewer',          'r')},
                {$this->getJoinColumnString('resource',          'i')},
                {$this->getJoinColumnString('resource.category', 'ic')},
                {$this->getJoinColumnString('resource.image',    'im')}
            FROM {$this->getTable()} `b`
            INNER JOIN {$this->getJoinSql('event',             'b', 'e')}
            LEFT  JOIN {$this->getJoinSql('event.owner',       'e', 'eo', true)}
            LEFT  JOIN {$this->getJoinSql('event.author',      'e', 'ea', true)}
            INNER JOIN {$this->getJoinSql('status',            'b', 's')}
            LEFT  JOIN {$this->getJoinSql('reviewer',          'b', 'r')}
            INNER JOIN {$this->getJoinSql('resource',          'b', 'i')}
            INNER JOIN {$this->getJoinSql('resource.category', 'i', 'ic')}
            LEFT  JOIN {$this->getJoinSql('resource.image',    'i', 'im')}
            WHERE b.deleted_at IS NULL
            AND   s.id IN {$this->getArrayPlaceholders($statusIds, 'status')}
            AND NOT (e.end_datetime < :startWindow OR e.start_datetime > :endWindow)
        ";
        if (!empty($extra['resource_id'])) {
            $query .= ' AND b.resource_id IN '
                    . $this->getArrayPlaceholders($extra['resource_id'], 'resource');
        }
        if (!empty($extra['category_id'])) {
            $query .= ' AND ic.id IN '
                    . $this->getArrayPlaceholders($extra['category_id'], 'category');
        }
        if (!empty($extra['owner_id'])) {
            $query .= ' AND eo.id IN '
                    . $this->getArrayPlaceholders($extra['owner_id'], 'owner');
        }
        $query .= ' ORDER BY e.start_datetime ASC';
        $statement = $this->getDatabase()->prepare($query);
        $statement->execute($params);

        // Hydration:
        // progress through each join that was passed.
        return $this->hydrateList(
            $statement, 'b',
            function($mapper, $model, $row) {
                /** @var self       $mapper */
                /** @var Model      $model */
                /** @var array      $row */
                /** @var Event      $event */
                /** @var Item\Model $resource */
                $event = $mapper->hydrateJoin($row, 'event', 'e');
                $event->setOwner($mapper->hydrateJoin($row, 'event.owner', 'eo'));
                $event->setAuthor($mapper->hydrateJoin($row, 'event.owner', 'ea'));
                $model->setEvent($event);
                $model->setStatus($mapper->hydrateJoin($row, 'status', 's'));
                $model->setReviewer($mapper->hydrateJoin($row, 'reviewer', 'r'));
                $resource = $mapper->hydrateJoin($row, 'resource', 'i', false);
                $resource->setCategory($mapper->hydrateJoin($row, 'resource.category', 'ic'));
                $resource->setImage($mapper->hydrateJoin($row, 'resource.image', 'im'));
                $model->setResource($resource);
                // Find all related bookings for this item too.
                // TODO do this without a query for each row
                $model->setRelatedBookingList($this->findRelatedBookings($model->getId()));
                return $model;
            }
        );
    }

    /**
     * Retrieves all bookings for the given owner.
     *
     * @param int   $ownerId
     * @param int   $fromTimestamp
     * @param int   $toTimestamp
     * @param array $statusIds     Status IDs of events to fetch. By default,
     *                             gets Status\Model::STATUS_PENDING and
     *                             Status\Model::STATUS_APPROVED only.
     *
     * @return Collection|Model[]
     */
    public function findForOwner($ownerId, $fromTimestamp, $toTimestamp, $statusIds = array())
    {
        return $this->findForTimespan(
            $fromTimestamp,
            $toTimestamp,
            $statusIds,
            array('owner_id' => array($ownerId))
        );
    }

    /**
     * Retrieves all bookings for the given category.
     *
     * @param int   $categoryId
     * @param int   $fromTimestamp
     * @param int   $toTimestamp
     * @param array $statusIds     Status IDs of events to fetch. By default,
     *                             gets Status\Model::STATUS_PENDING and
     *                             Status\Model::STATUS_APPROVED only.
     *
     * @return Collection|Model[]
     */
    public function findForCategory($categoryId, $fromTimestamp, $toTimestamp, $statusIds = array())
    {
        return $this->findForTimespan(
            $fromTimestamp,
            $toTimestamp,
            $statusIds,
            array('category_id' => array($categoryId))
        );
    }


    /**
     * Retrieves all bookings for the given resource.
     *
     * @param int[] $resourceIds
     * @param int   $fromTimestamp
     * @param int   $toTimestamp
     * @param array $statusIds     Status IDs of events to fetch. By default,
     *                             gets Status\Model::STATUS_PENDING and
     *                             Status\Model::STATUS_APPROVED only.
     *
     * @return Collection|Model[]
     */
    public function findForResource(array $resourceIds, $fromTimestamp, $toTimestamp, $statusIds = array())
    {
        return $this->findForTimespan(
            $fromTimestamp,
            $toTimestamp,
            $statusIds,
            array('resource_id' => $resourceIds)
        );
    }

    /**
     * Is the user a moderator of the item this booking is for?
     *
     * @param  int $bookingId The ID of the booking that is being checked (not the item's ID)
     * @param  int $userId    The ID of the user that is being checked
     * @return bool
     */
    public function isUserModeratorOfBookingItem($bookingId, $userId)
    {
        $query = "
            SELECT COUNT(*)
            FROM {$this->getTable()} b
            INNER JOIN {$this->getJoinSql('resource', 'b', 'r')}
            INNER JOIN {$this->getJoinSql('resource.moderator', 'r', 'rm')}
            WHERE b.id=:bookingId AND rm.moderator_id=:userId
        ";
        $statement = $this->getDatabase()->prepare($query);
        $statement->bindValue('bookingId', $bookingId);
        $statement->bindValue('userId', $userId);
        $statement->execute();
        return $statement->fetchColumn() > 0;
    }

    /**
     * Hydrates this item along with its event and resource.
     *
     * @param  int $id
     * @return Model
     * @throws BaseMapper\Exception if item doesn't exist
     */
    public function findById($id)
    {
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
        foreach ($statement as $row) {
            $model = $this->hydrate($row, 'b', function($mapper, $model, $row) {
                /** @var self  $mapper */
                /** @var Model $model */
                return $model
                    ->setResource($mapper->hydrateJoin($row, 'resource', 'r'))
                    ->setEvent($mapper->hydrateJoin($row, 'event', 'e'))
                ;
            });
            $this->throwIfDeleted($model);
            return $model;
        }
        throw new BaseMapper\Exception('Instance of object not found');
    }

    /**
     * Gets related bookings for this resource
     *
     * @param  int $id
     * @return Collection|Model[]
     */
    public function findRelatedBookings($id)
    {
        // Related bookings: all items that share the same event ID,
        // but are not the same booking
        $query = "
            SELECT
                {$this->getColumnString('b')},
                {$this->getJoinColumnString('resource', 'r')},
                {$this->getJoinColumnString('event',    'e')},
                {$this->getJoinColumnString('status',   's')}
            FROM {$this->getTable()} b
            INNER JOIN {$this->getTable()} rb
                ON b.event_id = rb.event_id AND b.id != rb.id AND rb.id = :bookingId
            INNER JOIN {$this->getJoinSql('resource', 'b', 'r')}
            INNER JOIN {$this->getJoinSql('event',    'b', 'e')}
            INNER JOIN {$this->getJoinSql('status',   'b', 's')}
            WHERE b.deleted_at IS NULL AND b.id != :bookingId
        ";
        $statement = $this->getDatabase()->prepare($query);
        $statement->bindValue('bookingId', $id);
        $statement->execute();
        return $this->hydrateList($statement, 'b', function($mapper, $model, $row) {
            /** @var self  $mapper */
            /** @var Model $model */
            return $model
                ->setResource($mapper->hydrateJoin($row, 'resource', 'r'))
                ->setEvent($mapper->hydrateJoin($row, 'event', 'e'))
                ->setStatus($mapper->hydrateJoin($row, 'status', 's'))
            ;
        });
    }

    /**
     * Finds all series bookings for resource booking $id, excluding booking $id.
     *
     * This only finds items that are for the same item that $booking is.
     *
     * @param  int $id
     * @param  int $seriesId
     * @return Collection|Model[]
     */
    public function findSeriesBookings($id, $seriesId)
    {
        $resourceStatement = $this->getDatabase()->prepare(
            "SELECT resource_id FROM {$this->getTable()} WHERE id=?"
        );
        $resourceStatement->execute(array($id));
        $resourceId = $resourceStatement->fetchColumn();
        if ($resourceId === false) {
            return new Collection();
        }


        // Only fetch series ID if booking $id is in series ID
        $query = "
            SELECT
                {$this->getColumnString('b')},
                {$this->getJoinColumnString('resource', 'r')},
                {$this->getJoinColumnString('event',    'e')}
            FROM {$this->getTable()} b
            INNER JOIN {$this->getJoinSql('resource', 'b', 'r')}
            INNER JOIN {$this->getJoinSql('event',    'b', 'e')}
            WHERE e.series_id = :seriesId
            AND   b.deleted_at IS NULL
            AND   b.id != :id
            AND   b.resource_id = :resourceId
        ";
        $statement = $this->getDatabase()->prepare($query);
        $statement->bindValue('id', $id);
        $statement->bindValue('seriesId', $seriesId);
        $statement->bindValue('resourceId', $resourceId);
        $statement->execute();
        $list = $this->hydrateList($statement, 'b', function($mapper, $model, $row) {
            /** @var self  $mapper */
            /** @var Model $model */
            return $model
                ->setResource($mapper->hydrateJoin($row, 'resource', 'r'))
                ->setEvent($mapper->hydrateJoin($row, 'event', 'e'))
            ;
        });
        return $list;
    }
}
