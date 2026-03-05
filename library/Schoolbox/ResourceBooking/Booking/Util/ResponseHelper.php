<?php

/**
 * Helper for use when creating response messages for requests to add/update bookings.
 */

namespace Schoolbox\ResourceBooking\Booking\Util;

use Schoolbox\ResourceBooking\Booking\Status;

class ResponseHelper
{
    /**
     * @param array<int, int> $nBookingsCreatedByStatusId - The number of bookings created, indexed by bookingStatusId.
     * @return string
     */
    public function getResponseMessage(array $nBookingsCreatedByStatusId): string
    {
        $nApprovedBookings = $nBookingsCreatedByStatusId[Status\Model::STATUS_APPROVED] ?? 0;
        $nPendingBookings = $nBookingsCreatedByStatusId[Status\Model::STATUS_PENDING] ?? 0;
        $nRejectedBookings = $nBookingsCreatedByStatusId[Status\Model::STATUS_REJECTED] ?? 0;
        $nTotalBookings = $nPendingBookings + $nApprovedBookings + $nRejectedBookings;

        if ($nTotalBookings === 0) {
            return 'Created event with no bookings';
        }

        if ($nTotalBookings === 1) {
            $bookingStatus = match ($nTotalBookings) {
                $nApprovedBookings => 'approved',
                $nPendingBookings => 'pending',
                $nRejectedBookings => 'rejected',
            };
            return "Created event with one $bookingStatus booking";
        }

        $messageEndingParts = [
            $nApprovedBookings === 0 ? '' : "$nApprovedBookings approved",
            $nPendingBookings  === 0 ? '' : "$nPendingBookings pending"  ,
            $nRejectedBookings === 0 ? '' : "$nRejectedBookings rejected",
        ];

        $messageEndingParts = array_filter($messageEndingParts, static fn ($messageEndingPart) => $messageEndingPart !== '');

        return "Created event with $nTotalBookings bookings - " . implode(', ', $messageEndingParts);
    }
}
