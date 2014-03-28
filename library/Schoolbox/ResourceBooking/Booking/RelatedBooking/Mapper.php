<?php
/**
 * Mapper for resource booking relations.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\ResourceBooking\Booking\RelatedBooking;
use Schoolbox\Mapper as BaseMapper;

class Mapper extends BaseMapper {

    public function mapping() {
        $this->hasTable('resource_booking_relation');
        $this->hasModel('\\Schoolbox\\ResourceBooking\\Booking\\RelatedBooking\\Model');
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
                'column'   => 'resource_booking_id',
                'property' => 'booking',
                'type'     => self::TYPE_MODEL,
                'join'     => 'booking',
                'join_def' => array(
                    'mapper'    => '\\Schoolbox\\ResourceBooking\\Booking\\Mapper',
                    'other_key' => 'id',
                ),
            )
        );
        $this->hasMap(
            array(
                'column'   => 'extra_resource_booking_id',
                'property' => 'relatedBooking',
                'type'     => self::TYPE_MODEL,
                'join'     => 'related_booking',
                'join_def' => array(
                    'mapper'    => '\\Schoolbox\\ResourceBooking\\Booking\\Mapper',
                    'other_key' => 'id',
                ),
            )
        );
        $this->registerCallback('postSave',   array($this, 'postSave'));
        $this->registerCallback('postDelete', array($this, 'postDelete'));
    }

    /**
     * Ensure that if booking A is related to booking B, then booking B is
     * related to booking A.
     *
     * @todo replace with ability to define this relation in the join
     *
     * @param Model $object
     */
    public function postSave(Model $object) {
        // I'm not sure if this covers the case where a relation's resource ID
        // is changed or not.
        // Oh well, links are only ever added or removed, never updated.
        $query = "
            INSERT INTO {$this->getTable()}
            (resource_booking_id, extra_resource_booking_id)
            VALUES (:bookingId, :relatedBookingId)
            ON DUPLICATE KEY UPDATE resource_booking_id = :bookingId
        ";
        $statement = $this->getDatabase()->prepare($query);
        $statement->bindValue(
            'bookingId',
            intval($object->getRelatedBooking()->getId()),
            \PDO::PARAM_INT
        );
        $statement->bindValue(
            'relatedBookingId',
            intval($object->getBooking()->getId()),
            \PDO::PARAM_INT
        );
        $statement->execute();
    }

    /**
     * Break the relation between booking B and booking A if the booking
     * between A and B is broken.
     *
     * @param Model $object
     */
    public function postDelete(Model $object) {
        $query = "
            DELETE FROM {$this->getTable()}
            WHERE resource_booking_id=:bookingId AND extra_booking_resource_id=:relatedBookingId
        ";
        $statement = $this->getDatabase()->prepare($query);
        $statement->bindValue(
            'bookingId',
            intval($object->getRelatedBooking()->getId()),
            \PDO::PARAM_INT
        );
        $statement->bindValue(
            'extrabookingId',
            intval($object->getBooking()->getId()),
            \PDO::PARAM_INT
        );
        $statement->execute();
    }

}
