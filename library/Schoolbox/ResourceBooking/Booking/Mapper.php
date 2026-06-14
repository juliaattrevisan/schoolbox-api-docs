<?php

/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\ResourceBooking\Booking;

use Alaress_Date_Format;
use PDO;
use Schoolbox\Calendar\Event;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Collection;
use Schoolbox\ResourceBooking\Item;

/**
 * Class Mapper
 *
 * A mapper for bookings.
 *
 * @package Schoolbox\ResourceBooking\Booking
 */
class Mapper extends BaseMapper
{
    /**
     * @inheritDoc
     */
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
        $this->hasMap(
            array(
                'column'   => 'quantity',
                'property' => 'quantity',
                'type'     => self::TYPE_INTEGER,
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

        // Booking start/end datetime (if the booking entry differs from the event's)
        $this->hasMap(
            array(
                'column'            => 'start_datetime',
                'property'          => 'bookingStartAt',
                'type'              => self::TYPE_DATETIME,
            )
        );
        $this->hasMap(
            array(
                'column'            => 'end_datetime',
                'property'          => 'bookingEndAt',
                'type'              => self::TYPE_DATETIME,
            )
        );

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
     * @return GenericCollection<Model>
     * @throws BaseMapper\DefinitionException
     * @throws \PDOException
     */
    public function findForTimespan($fromTimestamp, $toTimestamp, $statusIds = array(), $extra = array(), ?array $allowedEventTypes = null): GenericCollection
    {
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

        /* Ram in the allowedEventTypes if meaningful
         */
        if (null !== $allowedEventTypes && \count($allowedEventTypes)) {
            $params = array_merge($params, $this->getArrayParameters($allowedEventTypes, 'type'));
        }

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
            AND   " . ((count($statusIds) === 0) ? 'FALSE' : "s.id IN {$this->getArrayPlaceholders($statusIds, 'status')}") . "
            AND NOT (COALESCE(b.end_datetime, e.end_datetime) < :startWindow OR COALESCE(b.start_datetime, e.start_datetime) > :endWindow)
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

        /* Do a detection on $allowedEventTypes, if provided and meaningful
         */
        if (null !== $allowedEventTypes && \count($allowedEventTypes)) {
            $query .= ' AND e.type IN '
                . $this->getArrayPlaceholders($allowedEventTypes, 'type');
        }

        $query .= ' ORDER BY COALESCE(b.start_datetime, e.start_datetime) ASC';

        $statement = $this->getDatabase()->prepare($query);
        $statement->execute($params);

        // Hydration:
        // progress through each join that was passed.
        return $this->hydrateList(
            $statement,
            'b',
            /**
             * @param self $mapper
             * @param Model $model
             * @param array $row
             * @return Model
             */
            function ($mapper, $model, $row) {
                /** @var Event $event */
                $event = $mapper->hydrateJoin($row, 'event', 'e');
                $event->setOwner($mapper->hydrateJoin($row, 'event.owner', 'eo'));
                $event->setAuthor($mapper->hydrateJoin($row, 'event.owner', 'ea'));
                $model->setEvent($event);
                $model->setStatus($mapper->hydrateJoin($row, 'status', 's'));
                $model->setReviewer($mapper->hydrateJoin($row, 'reviewer', 'r'));

                /** @var Item\Model $resource */
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
     * @param array|null $allowedEventTypes  A set of lutEventType ids which filter the result set
     *
     * @return GenericCollection<Model>
     * @throws BaseMapper\DefinitionException
     * @throws \PDOException
     */
    public function findForOwner($ownerId, $fromTimestamp, $toTimestamp, $statusIds = array(), ?array $allowedEventTypes = null): GenericCollection
    {
        return $this->findForTimespan(
            $fromTimestamp,
            $toTimestamp,
            $statusIds,
            array('owner_id' => array($ownerId)),
            $allowedEventTypes
        );
    }


    /**
     * Retrieves all bookings for the given category.
     *
     * @param int        $categoryId
     * @param int        $fromTimestamp
     * @param int        $toTimestamp
     * @param array      $statusIds Status IDs of events to fetch. By default,
     *                              gets Status\Model::STATUS_PENDING and
     *                              Status\Model::STATUS_APPROVED only.
     * @param array|null $allowedEventTypes    A set of lutEventType ids which filter the result set
     * @return Collection|Model[]
     * @throws BaseMapper\DefinitionException
     * @throws \PDOException
     */
    public function findForCategory($categoryId, $fromTimestamp, $toTimestamp, $statusIds = array(), ?array $allowedEventTypes = null)
    {
        return $this->findForTimespan(
            $fromTimestamp,
            $toTimestamp,
            $statusIds,
            array('category_id' => array($categoryId)),
            $allowedEventTypes
        );
    }


    /**
     * Retrieves all bookings for the given resource.
     *
     * @param int[]      $resourceIds
     * @param int        $fromTimestamp
     * @param int        $toTimestamp
     * @param array      $statusIds Status IDs of events to fetch. By default,
     *                              gets Status\Model::STATUS_PENDING and
     *                              Status\Model::STATUS_APPROVED only.
     * @param array|null $allowedEventTypes    A set of lutEventType ids which filter the result set
     * @return Collection|Model[]
     * @throws BaseMapper\DefinitionException
     * @throws \PDOException
     */
    public function findForResource(array $resourceIds, $fromTimestamp, $toTimestamp, $statusIds = array(), ?array $allowedEventTypes = null)
    {
        return $this->findForTimespan(
            $fromTimestamp,
            $toTimestamp,
            $statusIds,
            array('resource_id' => $resourceIds),
            $allowedEventTypes
        );
    }


    /**
     * Is the user a moderator of the item this booking is for?
     *
     * @param int $bookingId The ID of the booking that is being checked (not the item's ID)
     * @param int $userId    The ID of the user that is being checked
     * @return bool
     * @throws BaseMapper\DefinitionException
     * @throws \PDOException
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
            $model = $this->hydrate($row, 'b', function ($mapper, $model, $row) {
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
        throw new BaseMapper\Exception\NotFound();
    }


    /**
     * Returns a collection of resource booking objects, each with their resource and status objects hydrated.
     *
     * @param  int $eventId
     * @return GenericCollection<Model>
     */
    public function findByEventId(int $eventId): GenericCollection
    {
        $query = "
            SELECT
                {$this->getColumnString('b')},
                {$this->getJoinColumnString('resource', 'r')},
                {$this->getJoinColumnString('status', 's')}
            FROM {$this->getTable()} b
            INNER JOIN {$this->getJoinSql('resource', 'b', 'r')}
            INNER JOIN {$this->getJoinSql('status', 'b', 's')}
            WHERE b.event_id=:eventId
            AND b.deleted_at IS NULL
            AND r.deleted_at IS NULL
        ";
        $statement = $this->getDatabase()->prepare($query);
        $statement->bindValue('eventId', $eventId);
        $statement->execute();

        $collection = new GenericCollection(Model::class);
        foreach ($statement as $row) {
            try {
                $bookingModel = $this->hydrate($row, 'b', function ($mapper, $model, $row) {
                    /** @var self $mapper */
                    /** @var Model $model */
                    /** @var Item\Model $resource */
                    $resource = $mapper->hydrateJoin($row, 'resource', 'r');
                    $model->setResource($resource);
                    /** @var Status\Model $status */
                    $status = $mapper->hydrateJoin($row, 'status', 's');
                    $model->setStatus($status);
                    return $model;
                });
            } catch (BaseMapper\Exception $e) {
                continue; // Skip the problem booking and move on to the next one.
            }

            if ($bookingModel !== null) {
                $collection->add($bookingModel);
            }
        }

        return $collection;
    }


    /**
     * Find all booking ids matching a given resource id and array of event ids.
     * This is for finding all bookings of a resource in a series of events.
     *
     * @param int        $resourceId
     * @param array<int> $eventIds
     * @param bool       $futureBookingsOnly Set to true to only include bookings that finish in the future.
     * @return array<int>
     */
    public function findIdsByResourceIdForEvents(
        int $resourceId,
        array $eventIds,
        bool $futureBookingsOnly = false
    ): array {
        if (count($eventIds) === 0) {
            return [];
        }

        $query = "
            SELECT b.id
            FROM {$this->getTable()} b
            INNER JOIN {$this->getJoinSql('event', 'b', 'e')}
            WHERE b.resource_id=:resourceId
            AND b.event_id IN (" . implode(',', array_map('intval', $eventIds)) . ")
            AND b.deleted_at IS NULL
            AND e.deleted_at IS NULL
        ";
        $params = [
            'resourceId' => $resourceId,
        ];

        if ($futureBookingsOnly) {
            // If booking start/end datetimes are not set, fall back to event start/end datetimes.
            $query .= " AND COALESCE(b.end_datetime, e.end_datetime)>NOW()";
        }
        $statement = $this->getDatabase()->prepare($query);
        $statement->execute($params);

        return $statement->fetchAll(PDO::FETCH_COLUMN);
    }


    /**
     * Return a data structure allowing quick lookup of which resources are booked for a set of events.
     *
     * @param $eventIds array<int> An array of event ids.
     * @return array<int, array<int>> An array of arrays of resource ids, indexed by event id.
     */
    public function getBookedResourceIdsByEventId(array $eventIds): array
    {
        if (count($eventIds) === 0) {
            return [];
        }

        $query = '
            SELECT event_id, resource_id
            FROM resource_booking rb
            JOIN event e ON (rb.event_id = e.id AND e.deleted_at IS NULL)
            WHERE event_id IN (' . implode(',', array_map('intval', $eventIds)) . ')
            AND rb.deleted_at IS NULL;
        ';
        $statement = $this->getDatabase()->prepare($query);
        $statement->execute();

        $resourceIdsByEventId = [];

        foreach ($statement as $row) {
            if (!array_key_exists($row['event_id'], $resourceIdsByEventId)) {
                $resourceIdsByEventId[$row['event_id']] = [];
            }

            $resourceIdsByEventId[$row['event_id']][] = $row['resource_id'];
        }

        return $resourceIdsByEventId;
    }


    /**
     * Return an array of collections of ResourceBookingModels, indexed by eventId.
     *
     * @param  array<int> $eventIds
     * @return array<int, GenericCollection<Model>>
     */
    public function getForAllEventIds(array $eventIds): array
    {
        if (count($eventIds) === 0) {
            return [];
        }

        // An 'ORDER BY' clause is added here because the order set will be desired in most cases, and because
        // returning items in a consistent order ensures consistent results, which is important for testing.
        $query = "
            SELECT
                {$this->getColumnString('b')},
                {$this->getJoinColumnString('resource', 'r')}
            FROM {$this->getTable()} b
            INNER JOIN {$this->getJoinSql('resource', 'b', 'r')}
            WHERE b.event_id IN (" . implode(',', array_map('intval', $eventIds)) . ")
            AND b.deleted_at IS NULL
            AND r.deleted_at IS NULL
            ORDER BY r.name ASC, b.id ASC
        ";
        $statement = $this->getDatabase()->prepare($query);
        $statement->execute();

        // Pre-fill the array to be returned, with empty collections indexed by eventId.
        // The calling code can then rely on the existence of a key for each supplied eventId.
        $collectionsByEventId = [];
        foreach ($eventIds as $eventId) {
            $collectionsByEventId[$eventId] = new GenericCollection(Model::class);
        }

        foreach ($statement as $row) {
            try {
                $bookingModel = $this->hydrate($row, 'b', function ($mapper, $model, $row) {
                    /** @var self $mapper */
                    /** @var Model $model */
                    /** @var Item\Model $resource */
                    $resource = $mapper->hydrateJoin($row, 'resource', 'r');
                    $model->setResource($resource);
                    return $model;
                });
            } catch (BaseMapper\Exception) {
                continue; // Skip the problem booking and move on to the next one.
            }

            if ($bookingModel === null) {
                //  Skip the problem booking and move on to the next one.
                continue;
            }

            $collectionsByEventId[$row['b.event_id']]->add($bookingModel);
        }

        return $collectionsByEventId;
    }


    /**
     * Gets related bookings for this resource
     *
     * @param int $id
     * @return Collection|Model[]
     * @throws BaseMapper\DefinitionException
     * @throws \PDOException
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
        return $this->hydrateList($statement, 'b', function ($mapper, $model, $row) {
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
     * @param int $id
     * @param int $seriesId
     * @return Collection|Model[]
     * @throws BaseMapper\DefinitionException
     * @throws \PDOException
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
        $list = $this->hydrateList($statement, 'b', function ($mapper, $model, $row) {
            /** @var self  $mapper */
            /** @var Model $model */
            return $model
                ->setResource($mapper->hydrateJoin($row, 'resource', 'r'))
                ->setEvent($mapper->hydrateJoin($row, 'event', 'e'))
            ;
        });
        return $list;
    }

    /**
     * @param array $resourceIds
     * @param bool  $sortByStartDatetimeAsc Set to true to have the array sorted by start time asc.
     *              This has been made a parameter so that if sorting is important in the calling
     *              code, that can be made clear to the reader by use of a named parameter.
     *              Eg. getPeriodsOfFutureApprovedBookingsByResourceId($resourceIds, sortByStartDatetimeAsc: true)
     * @return array
     */
    public function getPeriodsOfFutureApprovedBookingsByResourceId(
        array $resourceIds,
        bool $sortByStartDatetimeAsc = false,
        array $bookingIdsToExclude = [],
    ): array {
        if (count($resourceIds) === 0) {
            return [];
        }

        // Get the periods of all approved bookings of the given resources that end in the future.
        $query = "
            SELECT
            rb.resource_id,
            COALESCE(rb.start_datetime, e.start_datetime) AS start_datetime,
            COALESCE(rb.end_datetime, e.end_datetime) AS end_datetime,
            rb.quantity
            FROM resource_booking rb
            JOIN event e ON e.id = rb.event_id AND e.deleted_at IS NULL
            WHERE rb.resource_booking_status_id = :statusId
            AND rb.deleted_at IS NULL
            AND rb.resource_id IN (" . implode(',', array_map('intval', $resourceIds)) . ")
            AND COALESCE(rb.end_datetime, e.end_datetime) > NOW()
            " . ($bookingIdsToExclude ? "AND rb.id NOT IN (" . implode(',', array_map('intval', $bookingIdsToExclude)) . ")" : '') . "
            " . ($sortByStartDatetimeAsc ? 'ORDER BY COALESCE(rb.start_datetime, e.start_datetime) ASC' : '') . '
        ';
        $statement = $this->getDatabase()->prepare($query);
        $statement->execute(['statusId' => Status\Model::STATUS_APPROVED]);

        $periodsByResourceId = [];
        foreach ($statement as $row) {
            $periodsByResourceId[$row['resource_id']][] = [
                'startDatetime' => new Alaress_Date_Format($row['start_datetime'], Alaress_Date_Format::DATABASE_FORMAT),
                'endDatetime' => new Alaress_Date_Format($row['end_datetime'], Alaress_Date_Format::DATABASE_FORMAT),
                'quantity' => (int)$row['quantity'],
            ];
        }

        return $periodsByResourceId;
    }

    /**
     * Return the id of an etoc_event row linked to the given resource_booking.id, or null if no such row exists.
     * Use this function either to get the linked EOTC event id, or to check whether one exists.
     *
     * @param int $bookingId
     * @return null | int
     */
    public function getEotcEventId(int $bookingId): ?int
    {
        $query = "
            SELECT eotc_e_m.event_id
            FROM eotc_metadata_resourcebooking eotc_m_rb
            JOIN eotc_event_metadata eotc_e_m ON eotc_m_rb.metadata_id = eotc_e_m.metadata_id
            WHERE eotc_m_rb.booking_id = :bookingId
        ";
        $statement = $this->getDatabase()->prepare($query);
        $statement->execute(['bookingId' => $bookingId]);
        $rows = $statement->fetchAll();

        if (count($rows) === 0) {
            return null;
        }

        return (int)$rows[0]['event_id'];
    }
}
