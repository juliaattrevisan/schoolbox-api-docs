<?php

/**
 * Helper for use when generating response messages for requests to add or update bookings.
 */

namespace Schoolbox\ResourceBooking\Booking\Util;

use Schoolbox\ResourceBooking\Booking\Status;

class ResponseHelper
{
    private const string RESPONSE_TYPE_CREATE = 'create';
    private const string RESPONSE_TYPE_UPDATE = 'update';

    /**
     * @param array<int, int> $nBookingsCreatedByStatusId - The number of bookings created, indexed by bookingStatusId.
     * @param bool            $createdEvent - Whether an event was created also.
     * @return string
     */
    public function getCreateResponseMessage(array $nBookingsCreatedByStatusId, bool $createdEvent): string
    {
        return $this->getCreateOrUpdateResponseMessage($nBookingsCreatedByStatusId, self::RESPONSE_TYPE_CREATE, $createdEvent);
    }

    /**
     * @param array $nBookingsUpdatedByStatusId
     * @return string
     */
    public function getUpdateResponseMessage(array $nBookingsUpdatedByStatusId): string
    {
        return $this->getCreateOrUpdateResponseMessage($nBookingsUpdatedByStatusId, self::RESPONSE_TYPE_UPDATE);
    }

    /**
     * @param array  $nBookingsModifiedByStatusId - The number of bookings updated, indexed by bookingStatusId.
     * @param string $responseType
     * @param bool   $createdEvent
     * @return string
     */
    private function getCreateOrUpdateResponseMessage(
        array $nBookingsModifiedByStatusId,
        string $responseType,
        bool $createdEvent = false
    ): string {
        $nApprovedBookings = $nBookingsModifiedByStatusId[Status\Model::STATUS_APPROVED] ?? 0;
        $nPendingBookings = $nBookingsModifiedByStatusId[Status\Model::STATUS_PENDING] ?? 0;
        $nRejectedBookings = $nBookingsModifiedByStatusId[Status\Model::STATUS_REJECTED] ?? 0;
        $nTotalBookings = $nPendingBookings + $nApprovedBookings + $nRejectedBookings;

        if ($createdEvent && $responseType === self::RESPONSE_TYPE_UPDATE) {
            throw new \UnexpectedValueException(
                'Parameter $createdEvent should only be true if response type is RESPONSE_TYPE_CREATE.'
            );
        }

        if ($nTotalBookings === 0) {
            return match ($responseType) {
                self::RESPONSE_TYPE_CREATE => $createdEvent ? 'Created event with no bookings' : 'Could not create any bookings',
                self::RESPONSE_TYPE_UPDATE => 'Could not update any bookings',
                default => throw new \UnexpectedValueException('Unexpected response type: ' . $responseType),
            };
        }

        $messageStart = match ($responseType) {
            self::RESPONSE_TYPE_CREATE => $createdEvent ? 'Created event with' : 'Created',
            self::RESPONSE_TYPE_UPDATE => 'Updated',
            default => throw new \UnexpectedValueException('Unexpected response type: ' . $responseType),
        };

        if ($nTotalBookings === 1) {
            $bookingStatus = match ($nTotalBookings) {
                $nApprovedBookings => 'approved',
                $nPendingBookings => 'pending',
                $nRejectedBookings => 'rejected',
                default => throw new \LogicException('This state is impossible, but must be covered to satisfy PHPStan.')
            };
            return "$messageStart one $bookingStatus booking";
        }

        $messageEndingParts = [
            $nApprovedBookings === 0 ? '' : "$nApprovedBookings approved",
            $nPendingBookings  === 0 ? '' : "$nPendingBookings pending"  ,
            $nRejectedBookings === 0 ? '' : "$nRejectedBookings rejected",
        ];

        $messageEndingParts = array_filter($messageEndingParts, static fn ($messageEndingPart) => $messageEndingPart !== '');

        return "$messageStart $nTotalBookings bookings - " . implode(', ', $messageEndingParts);
    }
}
